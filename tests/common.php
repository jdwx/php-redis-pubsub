<?php


use JDWX\RedisPubSub\IRedisPubSub;


require '../vendor/autoload.php';

function GetRedis( bool $i_bAuth, bool $i_bTLS ) : IRedisPubSub {
    if ( $i_bTLS ) {
        $rps = new JDWX\RedisPubSub\RedisPubSub(
            'localhost', 6379,
            './client.crt',
            './client.key',
            './ca.crt'
        );
    } else {
        $rps = new JDWX\RedisPubSub\RedisPubSub( 'localhost', 6379 );
    }
    if ( $i_bAuth ) {
        $rps->auth( 'open-sesame' );
    }
    return $rps;
}
