# php-redis-pubsub

This is a lightweight PHP client for asynchronous Redis pub/sub functionality
with TLS and authentication.  It uses PHP's inbuilt stream functions and has 
no external dependencies.

The target use case is for a long-running process that is subscribed to one or
more Redis channels and needs to process incoming messages in a timely 
fashion, but also has other stuff to do.

It also supports a simple publishing interface.  This is suitable for apps 
that need to publish messages to a Redis channel, but don't otherwise
interact with Redis.

This is *not* intended to be a full-featured Redis client.  That space is 
extremely well covered by other extensions and packages.

This package requires PHP 8.0 or later.

## Installation

TBD.

## Usage

### Publisher:
```php
use JDWX\RedisPubSub\RedisPubSub;

$rps = new RedisPubSub( 'localhost' );
$rps->publish( 'test', 'Hello, world!' );
```

### Subscriber:
```php
use JDWX\RedisPubSub\RedisPubSub;

$rps = new RedisPubSub( 'localhost' );
$rps->subscribe( 'test' );

# This will block until a message is received.
$msg = $rps->recv();

# This will not block if there are no pending messages.
$msg = $rps->tryRecv();

# This will wait for up to 5 seconds for a message to return.
$msg = $rps->tryRecv( 5.0 );

# This will wait for a message for up to 5 seconds without returning it.
$msg = $rps->tryWait( 5.0 );

# A callback function.
function MyCallback( array $msg ) : void {
    var_dump( $msg );
}

# This will pass all messages currently in the queue to the callback function,
# one at a time.
$rps->recvAll( 'MyCallback' );

# This will wait 5 seconds for messages to arrive, passing them one at a time
# to the callback function.
$rps->recvAllWait( 5.0, 'MyCallback' );
```

## License

This library is licensed under the BSD 2-clause license.  See the 
[LICENSE](LICENSE) file for details.
