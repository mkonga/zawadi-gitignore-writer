<?php

declare(strict_types=1);

namespace Zawadi\GitignoreWriter;

class GitignoreWriter
{
    private const SMALLER = -1;
    private const BIGGER = 1;

    protected string $filename;
    protected string $section;
    protected FilesystemInterface $filesystem;

    /**
     * @param string $filename
     */
    public function __construct(string $filename, string $section, ?FilesystemInterface $filesystem = null)
    {
        $this->filename = $filename;
        $this->section = $section;
        if ($filesystem === null) {
            $filesystem = new Filesystem();
        }
        $this->filesystem = $filesystem;
    }

    /**
     * @param list<string> $add
     * @param list<string> $remove
     * @return void
     */
    public function updateSection(array $add = [], array $remove = [])
    {
        $originalEntries = $this->getEntries();
        $newEntries = array_unique(array_merge(array_diff($originalEntries, $remove), $add));
        usort($newEntries, ['self', 'compareEntries']);
        if ($originalEntries === $newEntries) {
            return;
        }
        $this->writeEntries($newEntries);
    }

    private function compareEntries(string $left, string $right): int
    {
        $leftIsInvert = substr($left, 0, 1) === '!';
        $rightIsInvert = substr($right, 0, 1) === '!';

        if ($leftIsInvert !== $rightIsInvert) {
            if ($leftIsInvert === true) {
                return self::BIGGER;
            }
            return self::SMALLER;
        }

        return $left <=> $right;
    }

    /**
     * @return list<string>
     */
    public function getEntries(): array
    {
        if (!$this->filesystem->isFile($this->filename)) {
            return [];
        }
        $fileContents = $this->filesystem->getFileContents($this->filename);
        if (!is_string($fileContents)) {
            return [];
        }

        $matches = [];
        $preg_match = preg_match(
            sprintf(
                '/###> %s >###(.*?)###< %s <###/ms',
                preg_quote($this->section, '/'),
                preg_quote($this->section, '/'),
            ),
            $fileContents,
            $matches,
        );

        if ($preg_match !== 1) {
            return [];
        }
        return array_values(array_filter(explode("\n", $matches[1])));
    }

    /**
     * @param list<string> $entries
     * @return void
     */
    private function writeEntries(array $entries): void
    {
        // file does not exist yet
        if (!$this->filesystem->isFile($this->filename)) {
            // nothing to put it, so we are done.
            if (count($entries) === 0) {
                return;
            }

            // something to put in, just write it to file.
            $this->filesystem->writeToFile($this->filename, $this->formatEntries($entries));
            return;
        }

        $contents = $this->filesystem->getFileContents($this->filename);
        if (!is_string($contents)) {
            $contents = '';
        }
        $newSection = $this->formatEntries($entries);
        $contents = preg_replace(
            sprintf(
                '/\n###> %s >###\n.*?\n###< %s <###\n/ms',
                preg_quote($this->section, '/'),
                preg_quote($this->section, '/'),
            ),
            $newSection,
            $contents,
            1,
            $count,
        );

        if ($count === 0) {
            // old section not found, just append the new section to the file
            $this->filesystem->appendToFile($this->filename, $newSection);
        } else {
            // old section was replaced, completely rewrite the file
            $this->filesystem->writeToFile($this->filename, $contents);
        }
    }

    /**
     * @param array<string> $entries
     * @return string
     */
    private function formatEntries(array $entries): string
    {
        if (count($entries) === 0) {
            return '';
        }

        return sprintf(
            '%s###> %s >###%s%s%s###< %s <###%s',
            PHP_EOL,
            $this->section,
            PHP_EOL,
            implode(PHP_EOL, $entries),
            PHP_EOL,
            $this->section,
            PHP_EOL,
        );
    }

    /**
     * @param list<string> $entries
     */
    public function replaceSection(array $entries = []): void
    {
        $this->writeEntries($entries);
    }
}
