<?php

namespace GuzzleHttp\Tests\Stream;

use krinfreschi\AsyncStreams\AsyncStreamWrapper;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;

/**
 * @covers GuzzleHttp\Stream\Stream
 */
class AsyncStreamTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var LoopInterface
     */
    private $loop;

    protected function setUp()
    {
        $this->loop = Factory::create();
        AsyncStreamWrapper::setLoop($this->loop);
    }

    protected function tearDown()
    {

    }

    /**
     * @test
     */
    public function itCloses()
    {
        $handle = $this->wrap(fopen('php://temp', 'w+'));
        fwrite($handle, 'data');
        async_stream_register_write($handle, function($handle) {
            fclose($handle);
            $this->assertFalse(is_resource($handle));
        });
        $this->loop->run();
    }

    /**
     * @test
     */
    public function itWritesAndReadsFromStream()
    {
        $handle = $this->wrap(fopen('php://temp', 'w+'));
        async_stream_register_write($handle, function($handle){
            fseek($handle, 0);
            $this->assertEquals('data', stream_get_contents($handle));
            fclose($handle);
        });
        fwrite($handle, 'data');
        $this->loop->run();
    }

    /**
     * @test
     */
    public function itReadsToEnd()
    {
        $handle = $this->wrap(fopen('php://temp', 'w+'));
        fwrite($handle, 'data');
        async_stream_register_write($handle, function($handle) {
            fseek($handle, 0);
            $this->assertFalse(feof($handle));
            fread($handle, 4);
            $this->assertTrue(feof($handle));
            fclose($handle);
        });
        $this->loop->run();
    }

    /**
     * @param resource $handle
     * @return resource
     */
    private function wrap($handle){
        return AsyncStreamWrapper::wrap($handle);
    }
}
