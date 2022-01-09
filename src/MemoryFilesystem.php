<?php

declare(strict_types=1);

namespace Zawadi\GitignoreWriter;

class MemoryFilesystem implements FilesystemInterface
{
    /**
     * @var array<string, string>
     */
    protected array $files = [];

    public function isFile(string $filename): bool
    {
        return array_key_exists($filename, $this->files);
    }

    public function getFileContents(string $filename)
    {
        if (!$this->isFile($filename)) {
            return false;
        }
        return $this->files[$filename];
    }

    public function writeToFile(string $filename, string $contents)
    {
        $this->files[$filename] = $contents;
        return strlen($contents);
    }

    public function appendToFile(string $filename, string $contents)
    {
        if (!$this->isFile($filename)) {
            $this->writeToFile($filename, $contents);
            return strlen($contents);
        }
        $this->files[$filename] .= $contents;
        return strlen($contents);
    }
}
