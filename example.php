<?php

use krinfreschi\AsyncStreams\AsyncStreamWrapper;

require_once "vendor/autoload.php";

$resource = fopen('php://temp', 'r+');
$handle = AsyncStreamWrapper::make($resource);
fwrite($handle, 'data');
async_stream_register_read($handle, function($handle) {});

async_stream_register_write($handle, function($handle, $written, $unwritten) {
    fseek($handle, 0);
    var_dump(stream_get_contents($handle));
    fclose($handle);
});

AsyncStreamWrapper::run();