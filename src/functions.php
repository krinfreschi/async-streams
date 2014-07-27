<?php

use krinfreschi\AsyncStreams\AsyncStreamWrapper;

if (!defined('ASYNC_STREAMS_FUNCTIONS')) {
    define('ASYNC_STREAMS_FUNCTIONS', true);

    /**
     * @param resource|object $handle
     * @param callable $callable
     * @throws InvalidArgumentException
     */
    function async_stream_register_read($handle, $callable){
        $wrapper = AsyncStreamWrapper::getFromResource($handle);
        if(!$wrapper || !is_callable($callable)){
            throw new InvalidArgumentException();
        }
        $wrapper->setOptions("read_callback", $callable);
    }

    /**
     * @param resource|object $handle
     * @param callable $callable
     * @throws InvalidArgumentException
     */
    function async_stream_register_write($handle, $callable){
        $wrapper = AsyncStreamWrapper::getFromResource($handle);
        if(!$wrapper && !is_callable($callable)){
            throw new InvalidArgumentException();
        }
        $wrapper->setOptions("write_callback", $callable);
    }

    /**
     * @param resource|object $handle
     * @throws InvalidArgumentException
     */
    function async_stream_remove_read($handle){
        $wrapper = AsyncStreamWrapper::getFromResource($handle);
        if(!$wrapper){
            throw new InvalidArgumentException();
        }
        $wrapper->setOptions("read_callback", null);
    }

    /**
     * @param resource|object $handle
     * @throws InvalidArgumentException
     */
    function async_stream_remove_write($handle){
        $wrapper = AsyncStreamWrapper::getFromResource($handle);
        if(!$wrapper){
            throw new InvalidArgumentException();
        }
        $wrapper->setOptions("write_callback", null);
    }


}