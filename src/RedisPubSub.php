<?php


declare( strict_types = 1 );


namespace JDWX\DAOS;


use Exception;


/**
 * Implements a minimal subset of the Redis RESP protocol for
 * lightweight handling of asynchronous publish/subscribe with
 * TLS support.
 */
class RedisPubSub {

    /** @var resource */
    protected $sock;


    public function __construct( string  $i_stHost, int $i_nPort = 6379, ?string $i_nstCertFile = null,
                                 ?string $i_nstKeyFile = null, ?string $i_nstCaFile = null,
                                 bool    $i_bVerifyPeerName = false ) {

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


    public function auth( string $i_stOne, ?string $i_stTwo = null ) : string {
        $r = [ $i_stOne ];
        if ( is_string( $i_stTwo ) ) {
            $r[] = $i_stTwo;
        }
        return $this->command( 'AUTH', $r );
    }


    public function psubscribe( string $i_stPattern ) : void {
        $this->commandNoReply( 'PSUBSCRIBE', [ $i_stPattern ] );
    }


    public function publish( string $i_stChannel, string $i_stMessage ) : int {
        return $this->command( 'PUBLISH', [ $i_stChannel, "\"$i_stMessage\"" ] );
    }


    public function punsubscribe( string $i_stPattern ) : void {
        $this->commandNoReply( 'PUNSUBSCRIBE', [ $i_stPattern ] );
    }


    public function recv() : array|int|string {
        $r = $this->recvInternal();
        if ( is_null( $r ) ) {
            throw new Exception( "Unexpected null response" );
        }
        return $r;
    }


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


    public function recvAll( callable $callback, bool $i_bMessagesOnly = false ) : void {
        while ( $x = $this->tryRecv() ) {
            if ( $i_bMessagesOnly && $x[ 0 ] !== 'message' && $x[ 0 ] !== 'pmessage' ) {
                continue;
            }
            $callback( $x );
        }
    }


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


    public function subscribe( array|string $i_channels ) : void {
        $this->commandNoReply( "SUBSCRIBE", $i_channels );
    }


    public function tryRecv( float|int $i_fTimeoutSeconds = 0 ) : array|int|string|null {
        if ( ! $this->tryWait( $i_fTimeoutSeconds ) ) {
            return null;
        }
        return $this->recv();
    }


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


    public function unsubscribe( array|string $i_channels ) : void {
        $this->commandNoReply( "UNSUBSCRIBE", $i_channels );
    }


    protected function command( string $i_stCommand, array|string $i_rArgs = [] ) : array|int|string {
        $this->commandNoReply( $i_stCommand, $i_rArgs );
        return $this->recv();
    }


    protected function commandNoReply( string $i_stCommand, array|string $i_rArgs = [] ) : void {
        if ( ! is_array( $i_rArgs ) ) {
            $i_rArgs = [ $i_rArgs ];
        }
        # $stCmd = strtoupper( $i_stCommand ) . " " . join( " ", array_map(
        #            function ( $x ) {
        #                return "\"{$x}\"";
        #            }, $i_rArgs )
        #    ) . "\r\n";
        $stCmd = strtoupper( $i_stCommand ) . " " . join( " ", $i_rArgs ) . "\r\n";
        var_dump( $stCmd );
        $rc = fwrite( $this->sock, $stCmd );
        if ( $rc === false ) {
            throw new Exception( "Error writing to socket" );
        }
    }


}
