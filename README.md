AsyncStreams
============

A stream wrapper around reactphp for async streams

With inspiration from [guzzle/streams](https://github.com/guzzle/streams)

Global Functions:

```php
async_stream_register_read(resource $handle, callable $callable) //$callable will receive args: $handle
async_stream_remove_read(resource $handle)
async_stream_register_write(resource $handle, callable $callable) //$callable will receive args: $handle, $written, $unwritten
async_stream_remove_write(resource $handle)
```

Example:

```php
use krinfreschi\AsyncStreams\AsyncStreamWrapper;

require_once "vendor/autoload.php";

$resource = fopen('php://temp', 'r+');
$handle = AsyncStreamWrapper::wrap($resource);
fwrite($handle, 'data');

async_stream_register_write($handle, function($handle, $written, $unwritten) {
    fseek($handle, 0);
    var_dump(stream_get_contents($handle));
});

AsyncStreamWrapper::run();
```
