<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\IteratorStream;
use Amp\ByteStream\Message;
use Amp\ByteStream\PendingReadError;
use Amp\Emitter;
use Amp\Loop;
use Amp\PHPUnit\TestCase;
use Amp\PHPUnit\TestException;
use Concurrent\Task;

class MessageTest extends TestCase
{
    public function testBufferingAll(): void
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new Message(new IteratorStream($emitter->iterate()));

        foreach ($values as $value) {
            $emitter->emit($value);
        }

        $emitter->complete();

        $this->assertSame(\implode($values), $stream->buffer());
    }

    public function testFullStreamConsumption(): void
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new Message(new IteratorStream($emitter->iterate()));

        foreach ($values as $value) {
            $emitter->emit($value);
        }

        Loop::delay(5, function () use ($emitter) {
            $emitter->complete();
        });

        $buffer = "";
        while (($chunk = $stream->read()) !== null) {
            $buffer .= $chunk;
        }

        $this->assertSame(\implode($values), $buffer);
        $this->assertSame("", $stream->buffer());
    }

    public function testFastResolvingStream(): void
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new Message(new IteratorStream($emitter->iterate()));

        foreach ($values as $value) {
            $emitter->emit($value);
        }

        $emitter->complete();

        $emitted = [];
        while (($chunk = $stream->read()) !== null) {
            $emitted[] = $chunk;
        }

        $this->assertSame($values, $emitted);
        $this->assertSame("", $stream->buffer());
    }

    public function testFastResolvingStreamBufferingOnly(): void
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new Message(new IteratorStream($emitter->iterate()));

        foreach ($values as $value) {
            $emitter->emit($value);
        }

        $emitter->complete();

        $this->assertSame(\implode($values), $stream->buffer());
    }

    public function testPartialStreamConsumption(): void
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new Message(new IteratorStream($emitter->iterate()));

        $emitter->emit($values[0]);

        $chunk = $stream->read();

        $this->assertSame(\array_shift($values), $chunk);

        foreach ($values as $value) {
            $emitter->emit($value);
        }

        $emitter->complete();

        $this->assertSame(\implode($values), $stream->buffer());
    }

    public function testFailingStream(): void
    {
        $exception = new TestException;
        $value = "abc";

        $emitter = new Emitter;
        $stream = new Message(new IteratorStream($emitter->iterate()));

        $emitter->emit($value);
        $emitter->fail($exception);

        $callable = $this->createCallback(1);

        try {
            while (($chunk = $stream->read()) !== null) {
                $this->assertSame($value, $chunk);
            }

            $this->fail("No exception has been thrown");
        } catch (TestException $reason) {
            $this->assertSame($exception, $reason);
            $callable(); // <-- ensure this point is reached
        }
    }

    public function testFailingStreamWithPendingRead(): void
    {
        $exception = new TestException;

        $emitter = new Emitter;
        $stream = new Message(new IteratorStream($emitter->iterate()));

        $readPromise = Task::async([$stream, 'read']);
        $emitter->fail($exception);

        $callable = $this->createCallback(1);

        try {
            Task::await($readPromise);

            $this->fail("No exception has been thrown");
        } catch (TestException $reason) {
            $this->assertSame($exception, $reason);
            $callable(); // <-- ensure this point is reached
        }
    }

    public function testEmptyStream(): void
    {
        $emitter = new Emitter;
        $emitter->complete();
        $stream = new Message(new IteratorStream($emitter->iterate()));

        $this->assertNull($stream->read());
    }

    public function testEmptyStringStream(): void
    {
        $value = "";

        $emitter = new Emitter;
        $stream = new Message(new IteratorStream($emitter->iterate()));

        $emitter->emit($value);

        $emitter->complete();

        $this->assertSame("", $stream->buffer());
    }

    public function testReadAfterCompletion(): void
    {
        $value = "abc";

        $emitter = new Emitter;
        $stream = new Message(new IteratorStream($emitter->iterate()));

        $emitter->emit($value);
        $emitter->complete();

        $this->assertSame($value, $stream->read());
        $this->assertNull($stream->read());
    }

    public function testGetInputStream(): void
    {
        $inputStream = new InMemoryStream("");
        $message = new Message($inputStream);

        $this->assertSame($inputStream, $message->getInputStream());
        $this->assertSame("", $message->getInputStream()->read());
    }

    public function testPendingRead(): void
    {
        $emitter = new Emitter;
        $stream = new Message(new IteratorStream($emitter->iterate()));

        Loop::delay(0, function () use ($emitter) {
            $emitter->emit("test");
        });

        $this->assertSame("test", $stream->read());
    }

    public function testPendingReadError(): void
    {
        try {
            $emitter = new Emitter;
            $stream = new Message(new IteratorStream($emitter->iterate()));
            $readOp = Task::async([$stream, 'read']);

            $this->expectException(PendingReadError::class);
            $stream->read();
        } catch (\Throwable $e) {
            Task::await($readOp);

            throw $e;
        }
    }
}
