<?php

declare(strict_types=1);

namespace Zawadi\GitIgnoreWriter\Tests;

use PHPUnit\Framework\TestCase;
use Zawadi\GitignoreWriter\Filesystem;

/**
 * @covers \Zawadi\GitignoreWriter\Filesystem
 * @uses \Zawadi\GitignoreWriter\FilesystemInterface
 */
final class FilesystemTest extends TestCase
{
    private Filesystem $filesystem;

    public function setUp(): void
    {
        $this->filesystem = new Filesystem();
    }

    public function testIsFile(): void
    {
        self::assertTrue($this->filesystem->isFile(__DIR__ . '/FilesystemTest.php'));
        self::assertFalse($this->filesystem->isFile(__DIR__ . '/FilesystemTest.txt'));
        self::assertFalse($this->filesystem->isFile(__DIR__));
    }

    public function testFileContents(): void
    {
        $filename = tempnam(sys_get_temp_dir(), 'phpunit_filesystemtest');
        self::assertTrue($this->filesystem->isFile($filename));
        self::assertEquals('', file_get_contents($filename));

        $contents = 'This is a dummy text' . PHP_EOL;
        $bytesWritten = $this->filesystem->writeToFile($filename, $contents);
        self::assertGreaterThan(0, $bytesWritten);
        self::assertEquals($contents, file_get_contents($filename));
        self::assertEquals($contents, $this->filesystem->getFileContents($filename));

        $extraContents = 'This is a second line';
        $bytesWritten = $this->filesystem->appendToFile($filename, $extraContents);
        self::assertGreaterThan(0, $bytesWritten);
        self::assertEquals($contents . $extraContents, file_get_contents($filename));
        self::assertEquals($contents . $extraContents, $this->filesystem->getFileContents($filename));

        unlink($filename);
    }
}
