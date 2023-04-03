<?php


declare( strict_types = 1 );


require 'common.php';


$rps = GetRedis( in_array( "auth", $argv ), in_array( "tls", $argv ) );


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
