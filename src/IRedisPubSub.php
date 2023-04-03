<?php


declare(strict_types=1);


namespace JDWX\RedisPubSub;


use Exception;


interface IRedisPubSub {


    /**
     * Performs Redis authentication.
     *
     * @param string      $i_stOne  If the only parameter, this is a password for simple authentication. If the
     *                              second parameter is present, this is the username for ACL authentication.
     * @param null|string $i_stTwo  If present, this is the password for ACL authentication.
     * @throws Exception            If authentication fails.
     */
    public function auth( string $i_stOne, ?string $i_stTwo = null ) : void;


    /**
     * Subscribe to a channel pattern.
     *
     * @param array|string $i_patterns A pattern or an array of patterns.
     * @throws Exception               If there is a networking error.
     */
    public function psubscribe( array|string $i_patterns ) : void;


    /**
     * Simple interface for publishing a message to a channel.
     *
     * @param string $i_stChannel   The channel to publish to.
     * @param string $i_stMessage   The message to publish.
     * @return int                  The number of subscribers to the channel (on the connected Redis node).
     * @throws Exception            If there is a networking error.
     */
    public function publish( string $i_stChannel, string $i_stMessage ) : int;


    /**
     * Unsubscribe from a channel pattern.
     *
     * @param string $i_stPattern   The pattern to unsubscribe from.
     * @throws Exception            If there is a networking error.
     */
    public function punsubscribe( string $i_stPattern ) : void;


    /**
     * Return one message from the subscription queue.  This will block if no messages are in the queue.
     * This is also used internally to decode Redis responses to commands outside the pub/sub context.
     *
     * @return array|int|string  The full response received from
     * @throws Exception         If there is a networking error.
     */
    public function recv() : array|int|string;


    /**
     * Retrieves all messages currently in the subscription queue and calls the provided callback
     * for each one.
     *
     * @param  callable $callback          The callback to call for each message.
     * @param  bool     $i_bMessagesOnly   If true, only message and pmessage responses are passed to the callback.
     * @throws Exception                   If there is a networking error.
     */
    public function recvAll( callable $callback, bool $i_bMessagesOnly = false ) : void;


    /**
     * Wait for the specified length of time, passing any messages that arrive during that time to the
     * provided callback.
     *
     * @param float|int $i_fTimeout         How long to wait (in seconds).
     * @param callable  $i_callback         The callback to call for each message.
     * @param bool      $i_bMessagesOnly    If true, only message and pmessage responses are passed to the callback.
     * @throws Exception                    If there is a networking error.
     */
    public function recvAllWait( float|int $i_fTimeout, callable $i_callback, bool $i_bMessagesOnly = false ) : void;


    /**
     * Subscribe to one or more channels.
     *
     * @param array|string $i_channels  The channel(s) to subscribe to.
     * @throws Exception                If there is a networking error.
     */
    public function subscribe( array|string $i_channels ) : void;


    /**
     * Wait up to the specified duration for a message to arrive, and return it if it does.
     *
     * @param float|int $i_fTimeoutSeconds  How long to wait (in seconds).
     * @return null|array|int|string        The message, or null if no message arrived.
     * @throws Exception                    If there is a networking error.
     */
    public function tryRecv( float|int $i_fTimeoutSeconds = 0 ) : array|int|string|null;


    /**
     * Wait up to the specified duration for a message to arrive, but leave it on the queue
     * if it does.
     *
     * @param float|int $i_fTimeoutSeconds  How long to wait (in seconds).
     * @return bool                         True if there is a message waiting, false if not.
     * @throws Exception
     */
    public function tryWait( float|int $i_fTimeoutSeconds = 0 ) : bool;


    /**
     * Unsubscribe from one or more channels.
     *
     * @param array|string $i_channels  The channel(s) to unsubscribe from.
     * @throws Exception                If there is a networking error.
     */
    public function unsubscribe( array|string $i_channels ) : void;



    }
