<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Stream;
use PHPUnit\Framework\TestCase;

/**
 * @covers GuzzleHttp\Psr7\LimitStream
 */
class LimitStreamTest extends TestCase
{
    /** @var LimitStream */
    private $body;

    /** @var Stream */
    private $decorated;

    protected function setUp(): void
    {
        $this->decorated = Psr7\stream_for(fopen(__FILE__, 'r'));
        $this->body = new LimitStream($this->decorated, 10, 3);
    }

    public function testReturnsSubset()
    {
        $body = new LimitStream(Psr7\stream_for('foo'), -1, 1);
        self::assertEquals('oo', (string) $body);
        self::assertTrue($body->eof());
        $body->seek(0);
        self::assertFalse($body->eof());
        self::assertEquals('oo', $body->read(100));
        self::assertSame('', $body->read(1));
        self::assertTrue($body->eof());
    }

    public function testReturnsSubsetWhenCastToString()
    {
        $body = Psr7\stream_for('foo_baz_bar');
        $limited = new LimitStream($body, 3, 4);
        self::assertEquals('baz', (string) $limited);
    }

    public function testReturnsSubsetOfEmptyBodyWhenCastToString()
    {
        $body = Psr7\stream_for('01234567891234');
        $limited = new LimitStream($body, 0, 10);
        self::assertEquals('', (string) $limited);
    }

    public function testReturnsSpecificSubsetOBodyWhenCastToString()
    {
        $body = Psr7\stream_for('0123456789abcdef');
        $limited = new LimitStream($body, 3, 10);
        self::assertEquals('abc', (string) $limited);
    }

    public function testSeeksWhenConstructed()
    {
        self::assertEquals(0, $this->body->tell());
        self::assertEquals(3, $this->decorated->tell());
    }

    public function testAllowsBoundedSeek()
    {
        $this->body->seek(100);
        self::assertEquals(10, $this->body->tell());
        self::assertEquals(13, $this->decorated->tell());
        $this->body->seek(0);
        self::assertEquals(0, $this->body->tell());
        self::assertEquals(3, $this->decorated->tell());
        try {
            $this->body->seek(-10);
            self::fail();
        } catch (\RuntimeException $e) {
        }
        self::assertEquals(0, $this->body->tell());
        self::assertEquals(3, $this->decorated->tell());
        $this->body->seek(5);
        self::assertEquals(5, $this->body->tell());
        self::assertEquals(8, $this->decorated->tell());
        // Fail
        try {
            $this->body->seek(1000, SEEK_END);
            self::fail();
        } catch (\RuntimeException $e) {
        }
    }

    public function testReadsOnlySubsetOfData()
    {
        $data = $this->body->read(100);
        self::assertEquals(10, strlen($data));
        self::assertSame('', $this->body->read(1000));

        $this->body->setOffset(10);
        $newData = $this->body->read(100);
        self::assertEquals(10, strlen($newData));
        self::assertNotSame($data, $newData);
    }

    public function testThrowsWhenCurrentGreaterThanOffsetSeek()
    {
        $a = Psr7\stream_for('foo_bar');
        $b = new NoSeekStream($a);
        $c = new LimitStream($b);
        $a->getContents();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not seek to stream offset 2');
        $c->setOffset(2);
    }

    public function testCanGetContentsWithoutSeeking()
    {
        $a = Psr7\stream_for('foo_bar');
        $b = new NoSeekStream($a);
        $c = new LimitStream($b);
        self::assertEquals('foo_bar', $c->getContents());
    }

    public function testClaimsConsumedWhenReadLimitIsReached()
    {
        self::assertFalse($this->body->eof());
        $this->body->read(1000);
        self::assertTrue($this->body->eof());
    }

    public function testContentLengthIsBounded()
    {
        self::assertEquals(10, $this->body->getSize());
    }

    public function testGetContentsIsBasedOnSubset()
    {
        $body = new LimitStream(Psr7\stream_for('foobazbar'), 3, 3);
        self::assertEquals('baz', $body->getContents());
    }

    public function testReturnsNullIfSizeCannotBeDetermined()
    {
        $a = new FnStream([
            'getSize' => function () {
                return null;
            },
            'tell'    => function () {
                return 0;
            },
        ]);
        $b = new LimitStream($a);
        self::assertNull($b->getSize());
    }

    public function testLengthLessOffsetWhenNoLimitSize()
    {
        $a = Psr7\stream_for('foo_bar');
        $b = new LimitStream($a, -1, 4);
        self::assertEquals(3, $b->getSize());
    }
}
