<?php


declare( strict_types = 1 );


require '../vendor/autoload.php';


if ( in_array( "tls", $argv ) ) {
    $rps = new JDWX\RedisPubSub\RedisPubSub (
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


$rps->subscribe([ 'test', 'test2' ]);
$rps->psubscribe( "test.*" );
for ( $i = 0; $i < 10; ++$i ) {
    $rps->recvAllWait( 1, 'MyCallback', true );
    echo "Tick ", $i, "\n";
}
$rps->unsubscribe([ 'test', 'test2' ]);
$rps->punsubscribe( 'test.*' );
$rps->recvAllWait( 5, 'MyCallback' );


function MyCallback( array $message ) : void {
    var_dump( $message );
}
