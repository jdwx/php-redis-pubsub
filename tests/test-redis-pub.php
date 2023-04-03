<?php



require '../vendor/autoload.php';


if ( in_array( "tls", $argv ) ) {
    $rps = new JDWX\RedisPubSub\RedisPubSub(
        'localhost', 6379,
        './client.crt',
        './client.key',
        './ca.crt'
    );
} else {
    $rps = new JDWX\RedisPubSub\RedisPubSub( 'localhost', 6379 );
}
if ( in_array( "auth", $argv ) ) {
    $rps->auth( 'open-sesame' );
}


$rChannels = [ 'test', 'test2', 'test.foo' ];
for ( $i = 0; $i < 10; ++$i ) {
    $stChannel = $rChannels[ array_rand( $rChannels ) ];
    $rps->publish( $stChannel, 'Hello, world ' . $i . ' to ' . $stChannel . ' from ' . posix_getpid() . '!' );
    sleep( 1 );
}
