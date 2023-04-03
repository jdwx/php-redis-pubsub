<?php


declare( strict_types = 1 );


namespace JDWX\RedisPubSub;


use Exception;


/**
 * Implements a minimal subset of the Redis RESP protocol for
 * lightweight handling of asynchronous publish/subscribe with
 * TLS support.
 */
class RedisPubSub implements IRedisPubSub {

    /** @var resource */
    protected $sock;


    /**
     * @param string      $i_stHost The hostname or IP address of the Redis server.
     * @param int         $i_nPort The port number of the Redis server.
     * @param null|string $i_nstCertFile The path to the certificate file (or PEM) if TLS is to be used.
     * @param null|string $i_nstKeyFile The path to the key file if TLS is to be used and the key is in
     *                                          a separate file.
     * @param null|string $i_nstCaFile The path to the CA file if TLS is to be used and the Redis server
     *                                          is using a self-signed certificate.
     * @param bool        $i_bVerifyPeerName If true, the peer name will be verified against the hostname.
     *                                          (This may not be desirable when an internal CA is used.)
     * @throws Exception                        If the connection fails.
     */
    public function __construct( string  $i_stHost = 'localhost', int $i_nPort = 6379,
                                 ?string $i_nstCertFile = null, ?string $i_nstKeyFile = null,
                                 ?string $i_nstCaFile = null, bool $i_bVerifyPeerName = false ) {

        $ctx = stream_context_create();
        if ( is_string( $i_nstCaFile ) || is_string( $i_nstKeyFile ) || is_string( $i_nstCertFile ) ) {
            $stURL = "ssl://{$i_stHost}:{$i_nPort}/";
        } else {
            $stURL = "tcp://{$i_stHost}:{$i_nPort}/";
        }
        if ( is_string( $i_nstCaFile ) ) {
            stream_context_set_option( $ctx, 'ssl', 'cafile', $i_nstCaFile );
            stream_context_set_option( $ctx, 'ssl', 'verify_peer', true );
            stream_context_set_option( $ctx, 'ssl', 'verify_peer_name', $i_bVerifyPeerName );
        }
        if ( is_string( $i_nstCertFile ) ) {
            stream_context_set_option( $ctx, 'ssl', 'local_cert', $i_nstCertFile );
        }
        if ( is_string( $i_nstKeyFile ) ) {
            stream_context_set_option( $ctx, 'ssl', 'local_pk', $i_nstKeyFile );
        }
        $x = stream_socket_client( $stURL, $errno, $error, 30, STREAM_CLIENT_CONNECT, $ctx );
        if ( $x === false ) {
            throw new Exception( "Error connecting to {$stURL}: {$errno} {$error}" );
        }
        $this->sock = $x;
    }


    public function __destruct() {
        fclose( $this->sock );
    }


    /** @inheritDoc */
    public function auth( string $i_stOne, ?string $i_stTwo = null ) : void {
        $r = [ $i_stOne ];
        if ( is_string( $i_stTwo ) ) {
            $r[] = $i_stTwo;
        }
        $x = $this->command( 'AUTH', $r );
        if ( "OK" == trim( $x ) ) {
            return;
        }
        throw new Exception( "Authentication failed: {$x}" );
    }


    /** @inheritDoc */
    public function psubscribe( array|string $i_patterns ) : void {
        $this->commandNoReply( 'PSUBSCRIBE', $i_patterns );
    }


    /** @inheritDoc */
    public function publish( string $i_stChannel, string $i_stMessage ) : int {
        return $this->command( 'PUBLISH', [ $i_stChannel, "\"$i_stMessage\"" ] );
    }


    /** @inheritDoc */
    public function punsubscribe( string $i_stPattern ) : void {
        $this->commandNoReply( 'PUNSUBSCRIBE', [ $i_stPattern ] );
    }


    /** @inheritDoc */
    public function recv() : array|int|string {
        $r = $this->recvInternal();
        if ( is_null( $r ) ) {
            throw new Exception( "Unexpected null response" );
        }
        return $r;
    }


    /** @inheritDoc */
    public function recvAll( callable $callback, bool $i_bMessagesOnly = false ) : void {
        while ( $x = $this->tryRecv() ) {
            if ( $i_bMessagesOnly && $x[ 0 ] !== 'message' && $x[ 0 ] !== 'pmessage' ) {
                continue;
            }
            $callback( $x );
        }
    }


    /** @inheritDoc */
    public function recvAllWait( float|int $i_fTimeout, callable $i_callback, bool $i_bMessagesOnly = false ) : void {
        $fNow = microtime( true );
        $fDone = $fNow + $i_fTimeout;
        while ( $fDone > $fNow ) {
            $fWait = $fDone - $fNow;
            if ( $this->tryWait( $fWait ) ) {
                $this->recvAll( $i_callback, $i_bMessagesOnly );
            }
            $fNow = microtime( true );
        }
    }


    /** @inheritDoc */
    public function subscribe( array|string $i_channels ) : void {
        $this->commandNoReply( "SUBSCRIBE", $i_channels );
    }


    /** @inheritDoc */
    public function tryRecv( float|int $i_fTimeoutSeconds = 0 ) : array|int|string|null {
        if ( ! $this->tryWait( $i_fTimeoutSeconds ) ) {
            return null;
        }
        return $this->recv();
    }


    /** @inheritDoc */
    public function tryWait( float|int $i_fTimeoutSeconds = 0 ) : bool {
        $r = [ $this->sock ];
        $w = [];
        $e = [];
        $iTimeoutSeconds = intval( $i_fTimeoutSeconds );
        $iTimeoutMicroseconds = intval( ( $i_fTimeoutSeconds - $iTimeoutSeconds ) * 1000000 );
        $rc = stream_select( $r, $w, $e, $iTimeoutSeconds, $iTimeoutMicroseconds );
        if ( $rc === false ) {
            throw new Exception( "Error selecting from socket" );
        }
        return ! empty( $r );
    }


    /** @inheritDoc */
    public function unsubscribe( array|string $i_channels ) : void {
        $this->commandNoReply( "UNSUBSCRIBE", $i_channels );
    }


    /**
     * Performs a Redis RESP command outside the pub/sub context.
     *
     * @param string       $i_stCommand The command to perform.
     * @param array|string $i_rArgs The arguments to the command.
     * @return array|int|string          The response.
     * @throws Exception                 If there is a networking error.
     */
    protected function command( string $i_stCommand, array|string $i_rArgs = [] ) : array|int|string {
        $this->commandNoReply( $i_stCommand, $i_rArgs );
        return $this->recv();
    }


    /**
     * Performs a Redis RESP command in the pub/sub context.
     *
     * @param string       $i_stCommand The command to perform.
     * @param array|string $i_rArgs The arguments to the command.
     * @throws Exception                If there is a networking error.
     */
    protected function commandNoReply( string $i_stCommand, array|string $i_rArgs = [] ) : void {
        if ( ! is_array( $i_rArgs ) ) {
            $i_rArgs = [ $i_rArgs ];
        }
        $stCmd = strtoupper( $i_stCommand ) . " " . join( " ", $i_rArgs ) . "\r\n";
        $rc = fwrite( $this->sock, $stCmd );
        if ( $rc === false ) {
            throw new Exception( "Error writing to socket" );
        }
    }


    /**
     * Internal method for decoding Redis responses.
     *
     * @return null|array|int|string   The message (or sub-message).
     * @throws Exception               If there is a networking error.
     */
    protected function recvInternal() : array|int|string|null {
        $stLine = fgets( $this->sock );
        if ( false === $stLine ) {
            throw new Exception( "Error reading from socket" );
        }
        switch ( $stLine[ 0 ] ) {
            case '*':
                $iCount = intval( substr( $stLine, 1 ) );
                $rLines = [];
                for ( $i = 0 ; $i < $iCount ; $i++ ) {
                    $rLines[] = $this->recv();
                }
                return $rLines;
            case '+':
                return substr( $stLine, 1 );
            case '-':
                throw new Exception( "Error: " . substr( $stLine, 1 ) );
            case ':':
                return intval( substr( $stLine, 1 ) );
            case '$':
                $iLength = intval( substr( $stLine, 1 ) );
                if ( $iLength === -1 ) {
                    return null;
                }
                $stLine = fread( $this->sock, $iLength + 2 );
                if ( false === $stLine ) {
                    throw new Exception( "Error reading from socket" );
                }
                return substr( $stLine, 0, -2 );
        }
        throw new Exception( "Unknown response type: {$stLine[ 0 ]}: " . substr( $stLine, 1 ) );
    }


}
