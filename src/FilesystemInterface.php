<?php

declare(strict_types=1);

namespace Zawadi\GitignoreWriter;

interface FilesystemInterface
{
    public function isFile(string $filename): bool;

    /**
     * @param string $filename
     * @return bool|string
     */
    public function getFileContents(string $filename);

    /**
     * @param string $filename
     * @param string $contents
     * @return bool|int
     */
    public function writeToFile(string $filename, string $contents);

    /**
     * @param string $filename
     * @param string $contents
     * @return bool|int
     */
    public function appendToFile(string $filename, string $contents);
}
