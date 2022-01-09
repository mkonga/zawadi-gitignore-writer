<?php

declare(strict_types=1);

namespace Zawadi\GitignoreWriter;

class Filesystem implements FilesystemInterface
{
    public function isFile(string $filename): bool
    {
        return is_file($filename);
    }

    public function getFileContents(string $filename)
    {
        return file_get_contents($filename);
    }

    public function writeToFile(string $filename, string $contents)
    {
        return file_put_contents($filename, $contents);
    }

    public function appendToFile(string $filename, string $contents)
    {
        return file_put_contents($filename, $contents, FILE_APPEND);
    }
}
