<?php

declare(strict_types=1);

namespace Zawadi\GitIgnoreWriter\Tests;

use PHPUnit\Framework\TestCase;
use Zawadi\GitignoreWriter\MemoryFilesystem;

/**
 * @covers \Zawadi\GitignoreWriter\MemoryFilesystem
 * @uses \Zawadi\GitignoreWriter\FilesystemInterface
 */
final class MemoryFilesystemTest extends TestCase
{
    private MemoryFilesystem $filesystem;

    public function setUp(): void
    {
        $this->filesystem = new MemoryFilesystem();
    }

    public function testIsFile(): void
    {
        self::assertFalse($this->filesystem->isFile('somefile.txt'));
        self::assertFalse($this->filesystem->getFileContents('somefile.txt'));
    }

    public function testWriteFile(): void
    {
        $filename = 'somefile.txt';
        $contents = 'somecontent' . PHP_EOL;
        $bytesWritten = $this->filesystem->writeToFile($filename, $contents);
        self::assertEquals(12, $bytesWritten);
        self::assertEquals($contents, $this->filesystem->getFileContents($filename));
        self::assertTrue($this->filesystem->isFile($filename));

        $bytesWritten = $this->filesystem->appendToFile($filename, $contents);
        self::assertEquals(12, $bytesWritten);
        self::assertEquals($contents . $contents, $this->filesystem->getFileContents($filename));

        $this->filesystem->writeToFile($filename, $contents);
        self::assertEquals($contents, $this->filesystem->getFileContents($filename));
    }

    public function testAppendToEmptyFile(): void
    {
        $filename = 'somefile.txt';
        $contents = 'somecontent' . PHP_EOL;
        $bytesWritten = $this->filesystem->appendToFile($filename, $contents);
        self::assertEquals(12, $bytesWritten);
        self::assertEquals($contents, $this->filesystem->getFileContents($filename));
        self::assertTrue($this->filesystem->isFile($filename));
    }
}
