<?php



require '../vendor/autoload.php';


$rps = new JDWX\DAOS\RedisPubSub(
    'localhost', 6379,
    './client.crt',
    './client.key',
    '../ca/ca.crt'
);
$rps->auth( 'open-sesame' );

$rChannels = [ 'test', 'test2', 'test.foo' ];
for ( $i = 0; $i < 10; ++$i ) {
    $stChannel = $rChannels[ array_rand( $rChannels ) ];
    $rps->publish( $stChannel, 'Hello, world ' . $i . ' to ' . $stChannel . ' from ' . posix_getpid() . '!' );
    sleep( 1 );
}

