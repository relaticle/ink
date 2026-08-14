<?php

declare(strict_types=1);

namespace Relaticle\Ink\Tests\Fixtures;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * A PSR-7 stream double that lazily generates bytes on read() instead of holding a
 * literal in-memory string, and counts exactly how many bytes were pulled from it.
 *
 * Http::fake() responses otherwise buffer their whole body in memory up front (a
 * plain string wrapped in a Psr7\Stream), which makes it impossible to prove
 * black-box that UploadImageTool::readCapped() actually aborts early rather than
 * reading to the end — every assertion on the *outcome* would pass identically
 * whether the read stopped at the cap or continued to a fully-buffered string. This
 * double makes the mechanism itself observable: assert bytesRead stayed far below
 * totalSize after a capped upload is rejected.
 */
final class CountingStream implements StreamInterface
{
    public int $bytesRead = 0;

    private int $position = 0;

    public function __construct(private readonly int $totalSize) {}

    public function read(int $length): string
    {
        $remaining = $this->totalSize - $this->position;
        $toRead = min($length, $remaining);

        $this->position += $toRead;
        $this->bytesRead += $toRead;

        return str_repeat('x', $toRead);
    }

    public function eof(): bool
    {
        return $this->position >= $this->totalSize;
    }

    public function close(): void {}

    public function detach()
    {
        return null;
    }

    public function getSize(): ?int
    {
        return $this->totalSize;
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new RuntimeException('CountingStream is not seekable.');
    }

    public function rewind(): void
    {
        throw new RuntimeException('CountingStream is not seekable.');
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        throw new RuntimeException('CountingStream is not writable.');
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function getContents(): string
    {
        return $this->read($this->totalSize - $this->position);
    }

    public function getMetadata(?string $key = null): mixed
    {
        return $key === null ? [] : null;
    }

    public function __toString(): string
    {
        return $this->getContents();
    }
}
