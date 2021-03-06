<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\ResourceInputStream;
use PHPUnit\Framework\TestCase;
use function Amp\Promise\wait;

class ResourceInputStreamTest extends TestCase
{
    public function testGetResource()
    {
        $stream = new ResourceInputStream(\STDIN);

        $this->assertSame(\STDIN, $stream->getResource());
    }

    public function testNonStream()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Expected a valid stream");

        new ResourceInputStream(42);
    }

    public function testNotReadable()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Expected a readable stream");

        new ResourceInputStream(\STDOUT);
    }

    public function testClosedRemoteSocketWithFork()
    {
        $server = \stream_socket_server("tcp://127.0.0.1:0");
        $address = \stream_socket_get_name($server, false);

        $a = \stream_socket_client("tcp://" . $address);
        $b = \stream_socket_accept($server);

        // Creates a fork without having to deal with it…
        // The fork inherits the FDs of the current process.
        $proc = \proc_open("sleep 3", [
            ['pipe', 'r'],
            ['pipe', 'w'],
            ['pipe', 'w'],
        ], $pipes);

        $stream = new ResourceInputStream($a);
        \stream_socket_shutdown($b, \STREAM_SHUT_RDWR);
        \fclose($b);

        try {
            // Read must succeed before the sub-process exits
            $start = \microtime(true);
            self::assertNull(wait($stream->read()));
            self::assertLessThanOrEqual(1, \microtime(true) - $start);
        } finally {
            \proc_terminate($proc);
            \proc_close($proc);
        }
    }
}
