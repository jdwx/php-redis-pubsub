<?php


require __DIR__ . '/common.php';


$rps = GetRedis( in_array( "auth", $argv ), in_array( "tls", $argv ) );


$rChannels = [ 'test', 'test2', 'test.foo' ];
for ( $i = 0; $i < 10; ++$i ) {
    $stChannel = $rChannels[ array_rand( $rChannels ) ];
    $rps->publish( $stChannel, 'Hello, world ' . $i . ' to ' . $stChannel . ' from ' . posix_getpid() . '!' );
    sleep( 1 );
}
