<?php


declare( strict_types = 1 );


require '../vendor/autoload.php';


$rps = new JDWX\DAOS\RedisPubSub(
    'localhost', 6379,
    './client.crt',
    './client.key',
    '../ca/ca.crt'
);
$rps->auth( 'open-sesame' );
$rps->subscribe([ 'test', 'test2' ]);
$rps->psubscribe( "test.*" );

function MyCallback( array $message ) : void {
    var_dump( $message );
}

for ( $i = 0; $i < 10; ++$i ) {
    $rps->recvAllWait( 1, 'MyCallback', true );
    echo "Tick ", $i, "\n";
}
$rps->unsubscribe([ 'test', 'test2' ]);
$rps->punsubscribe( 'test.*' );
$rps->recvAllWait( 5, 'MyCallback' );
