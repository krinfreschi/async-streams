<?php

namespace krinfreschi\AsyncStreams;

use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;

class AsyncStreamWrapper
{
    const WRAPPER_NAME = 'async';
    /**
     * @var resource
     */
    public $context;

    /**
     * @var stream|resource
     */
    private $stream;

    /**
     * @var string Read/Write mode
     */
    private $mode;

    private $meta;

    /**
     * @var int Size of the stream contents in bytes
     */
    private $size;

    /** @var bool */
    private $seekable;

    private $readable;
    private $writable;
    /** @var bool */
    private $isClosing = false;

    private $pendingWrites = [];
    private $pendingWritesSize = 0;

    //TODO: find better way of referencing current resource
    private static $streams = [];

    /**
     * @var LoopInterface
     */
    private static $loop;

    /** @var array Hash of readable and writable stream types */
    private static $readWriteHash = [
        'read' => [
            'r' => true, 'w+' => true, 'r+' => true, 'x+' => true, 'c+' => true,
            'rb' => true, 'w+b' => true, 'r+b' => true, 'x+b' => true,
            'c+b' => true, 'rt' => true, 'w+t' => true, 'r+t' => true,
            'x+t' => true, 'c+t' => true, 'a+' => true
        ],
        'write' => [
            'w' => true, 'w+' => true, 'rw' => true, 'r+' => true, 'x+' => true,
            'c+' => true, 'wb' => true, 'w+b' => true, 'r+b' => true,
            'x+b' => true, 'c+b' => true, 'w+t' => true, 'r+t' => true,
            'x+t' => true, 'c+t' => true, 'a' => true, 'a+' => true
        ]
    ];

    /**
     * @return LoopInterface
     */
    public function getLoop()
    {
        if (is_null(static::$loop)) {
            static::setLoop(Factory::create());
        }
        return static::$loop;
    }

    /**
     * @param LoopInterface $loop
     */
    public static function setLoop(LoopInterface $loop)
    {
        static::$loop = $loop;
    }

    /**
     * Run the event loop
     */
    public static function run()
    {
        static::$loop->run();
    }

    /**
     * @param $handle
     * @param callable $callable
     * @throws \InvalidArgumentException
     * @return resource
     */
    public static function make($handle, $callable = null)
    {
        if (!in_array('async', stream_get_wrappers())) {
            stream_wrapper_register(self::WRAPPER_NAME, __CLASS__);
        }

        stream_set_blocking($handle, 0);
        stream_set_read_buffer($handle, 0);
        stream_set_write_buffer($handle, 0);

        $meta = stream_get_meta_data($handle);
        $readable = isset(self::$readWriteHash['read'][$meta['mode']]);
        $writable = isset(self::$readWriteHash['write'][$meta['mode']]);

        if ($readable) {
            $mode = $writable ? 'r+' : 'r';
        } elseif ($writable) {
            $mode = 'w';
        } else {
            throw new \InvalidArgumentException('The stream must be readable, '
                . 'writable, or both.');
        }

        $asyncHandle = fopen(self::WRAPPER_NAME . '://stream', $mode, null, stream_context_create([
            self::WRAPPER_NAME => [
                'handle' => $handle,
            ],
        ]));

        $wrap = $asyncHandle;

        if(!is_null($callable) && is_callable($callable)){
            $ret = call_user_func($callable, $asyncHandle);
            if($ret && is_object($ret)){
                $wrap = $ret;
            }
        }

        static::$streams[(int)$handle] = $wrap;

        return $wrap;
    }


    /**
     * @param $cast_as
     * @return bool|stream|resource
     */
    public function stream_cast($cast_as)
    {
        switch ($cast_as) {
            case STREAM_CAST_FOR_SELECT:
            case STREAM_CAST_AS_STREAM:
                return $this->stream;
                break;
        }
        return false;
    }

    /**
     *
     */
    public function stream_close()
    {
        $this->isClosing = true;
        $this->getLoop()->futureTick(function () {
            $this->getLoop()->removeStream($this->stream);
            fclose($this->stream);
            unset(static::$streams[(int)$this->stream]);
        });
    }

    /**
     * @return bool
     */
    public function stream_eof()
    {
        return $this->isClosing || !is_resource($this->stream) || feof($this->stream);
    }

    /**
     * @param $path
     * @param $mode
     * @param $options
     * @param $opened_path
     * @return bool
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $options = $this->getOptions();

        if ($options === false || !isset($options['handle'])) {
            return false;
        }

        $this->stream = $options['handle'];
        $this->mode = $mode;
        switch ($this->mode) {
            case "r":
                $this->readable = true;
            case "w":
                $this->writable = true;
                break;
            case "r+":
                $this->readable = true;
                $this->writable = true;
                break;
        }
        $this->meta = stream_get_meta_data($this->stream);
        $this->seekable = (bool)$this->meta['seekable'];
        $this->getLoop()
            ->addReadStream($this->stream, function () {
                $this->asyncHandleRead();
            });
        $this->getLoop()
            ->addWriteStream($this->stream, function () {
                $this->asyncHandleWrite();
            });
        return true;
    }

    /**
     * @param $count
     * @return string
     */
    public function stream_read($count)
    {
        return $this->readable
            ? fread($this->stream, $count)
            : "";
    }

    /**
     * @param $offset
     * @param int $whence
     * @return bool
     */
    public function stream_seek($offset, $whence = SEEK_SET)
    {
        return $this->seekable
            ? fseek($this->stream, $offset, $whence) === 0
            : false;
    }

    /**
     * @return array
     */
    public function stream_stat()
    {
        static $modeMap = [
            'r' => 33060,
            'r+' => 33206,
            'w' => 33188
        ];

        return [
            'dev' => 0,
            'ino' => 0,
            'mode' => $modeMap[$this->mode],
            'nlink' => 0,
            'uid' => 0,
            'gid' => 0,
            'rdev' => 0,
            'size' => $this->getSize() ? : 0,
            'atime' => 0,
            'mtime' => 0,
            'ctime' => 0,
            'blksize' => 0,
            'blocks' => 0
        ];
    }

    /**
     * @return int
     */
    public function stream_tell()
    {
        return ftell($this->stream);
    }

    /**
     * @param $new_size
     * @return bool
     */
    public function stream_truncate($new_size)
    {
        return ftruncate($this->stream, $new_size);
    }

    /**
     * @param $data
     * @return bool|int
     */
    public function stream_write($data)
    {
        if ($this->writable) {
            array_push($this->pendingWrites, $data);
            return $this->pendingWritesSize += strlen($data);
        }
        return false;
    }

    /**
     * @param $option
     * @param $value
     * @return bool
     */
    public function setOptions($option, $value){
        return stream_context_set_option($this->context, self::WRAPPER_NAME, $option, $value);
    }

    /**
     * @return bool
     */
    public function getOptions(){
        $options = stream_context_get_options($this->context);
        return isset($options[self::WRAPPER_NAME]) ? $options[self::WRAPPER_NAME] : false;
    }

    /**
     * @return int|null
     */
    protected function getSize()
    {
        if ($this->size !== null) {
            return $this->size;
        } elseif (!$this->stream) {
            return null;
        }

        // If the stream is a file based stream and local, then use fstat
        if (isset($this->meta['uri'])) {
            clearstatcache(true, $this->meta['uri']);
        }

        $stats = fstat($this->stream);
        if (isset($stats['size'])) {
            $this->size = $stats['size'];
            return $this->size;
        }

        return null;
    }

    protected function getResource(){
        return static::$streams[(int)$this->stream];
    }

    protected function asyncHandleRead()
    {
        $options = $this->getOptions();
        if(isset($options["read_callback"]) && $options["read_callback"]){
            call_user_func($options["read_callback"], $this->getResource());
        }
    }

    protected function asyncHandleWrite()
    {
        if (!empty($this->pendingWrites)) {
            $this->size = null;
            $message = array_shift($this->pendingWrites);
            $written = fwrite($this->stream, $message);
            if ($written != strlen($message)) {
                array_unshift($this->pendingWrites, substr($message, $written));
            }
            $this->pendingWritesSize -= $written;
            $options = $this->getOptions();
            if(isset($options["write_callback"]) && $options["write_callback"]){
                call_user_func($options["write_callback"], $this->getResource(), $written, $this->pendingWritesSize);
            }
        }
    }

}
