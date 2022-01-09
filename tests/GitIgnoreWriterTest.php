<?php

declare(strict_types=1);

namespace Zawadi\GitIgnoreWriter\Tests;

use PHPUnit\Framework\TestCase;
use Zawadi\GitignoreWriter\GitignoreWriter;
use Zawadi\GitignoreWriter\MemoryFilesystem;

/**
 * @covers \Zawadi\GitignoreWriter\GitignoreWriter
 * @uses   \Zawadi\GitignoreWriter\FilesystemInterface
 * @uses   \Zawadi\GitignoreWriter\MemoryFilesystem
 */
final class GitIgnoreWriterTest extends TestCase
{
    public function testNoFile(): void
    {
        $filesystem = new MemoryFilesystem();
        $filename = './.gitignore';
        $gitignoreWriter = new GitignoreWriter($filename, 'mysection', $filesystem);

        // check new file contains no entries
        self::assertCount(0, $gitignoreWriter->getEntries());

        // add 2 entries and check they are stored
        $gitignoreWriter->updateSection(['/admin/', 'robots.txt']);
        self::assertEquals(['/admin/', 'robots.txt'], $gitignoreWriter->getEntries());
        self::assertTrue($filesystem->isFile($filename));

        // check contents of file
        $fileContents = (string)$filesystem->getFileContents($filename);
        self::assertStringContainsString('###> mysection >###', $fileContents);
        self::assertStringContainsString('/admin/', $fileContents);
        self::assertStringContainsString('robots.txt', $fileContents);
        self::assertStringContainsString('###< mysection <###', $fileContents);

        // remove an entry and check contents
        $gitignoreWriter->updateSection([], ['robots.txt']);
        self::assertEquals(['/admin/'], $gitignoreWriter->getEntries());
        $gitignoreWriter->updateSection([], ['/admin/']);
        self::assertEquals([], $gitignoreWriter->getEntries());

        // after removing everything, the file should be empty
        $fileContents = (string)$filesystem->getFileContents($filename);
        self::assertStringNotContainsString('###> mysection >###', $fileContents);
        self::assertStringNotContainsString('/admin/', $fileContents);
        self::assertStringNotContainsString('robots.txt', $fileContents);
        self::assertStringNotContainsString('###< mysection <###', $fileContents);
    }

    public function testAppend(): void
    {
        $filesystem = new MemoryFilesystem();
        $filename = './.gitignore';
        $gitignoreWriter = new GitignoreWriter($filename, 'mysection', $filesystem);

        $initialContent = 'Some random lines' . PHP_EOL .
            'More lines';

        // put something random in
        $filesystem->writeToFile($filename, $initialContent);

        // add single entry
        $gitignoreWriter->updateSection(['robots.txt']);
        self::assertEquals(['robots.txt'], $gitignoreWriter->getEntries());
        // check original content is still there
        self::assertStringStartsWith($initialContent . PHP_EOL, (string)$filesystem->getFileContents($filename));

        // check removal
        $gitignoreWriter->updateSection([], ['robots.txt']);
        self::assertEquals([], $gitignoreWriter->getEntries());
        self::assertEquals($initialContent, $filesystem->getFileContents($filename));
    }

    public function testRemovalBetween(): void
    {
        $filesystem = new MemoryFilesystem();
        $filename = './.gitignore';
        $gitignoreWriter = new GitignoreWriter($filename, 'mysection', $filesystem);

        $initialContent = 'Some random lines' . PHP_EOL .
            'More lines' . PHP_EOL;
        $endingContent = PHP_EOL . 'More random lines' . PHP_EOL .
            'Something different' . PHP_EOL;

        $filesystem->writeToFile($filename, $initialContent);
        $gitignoreWriter->updateSection(['robots.txt']);
        $filesystem->appendToFile($filename, $endingContent);

        // check file contents
        self::assertEquals(['robots.txt'], $gitignoreWriter->getEntries());
        $fileContents = (string)$filesystem->getFileContents($filename);
        self::assertStringContainsString($initialContent, $fileContents);
        self::assertStringContainsString($endingContent, $fileContents);

        // remove section
        $gitignoreWriter->updateSection([], ['robots.txt']);
        self::assertEquals([], $gitignoreWriter->getEntries());
        $fileContents = (string)$filesystem->getFileContents($filename);
        self::assertStringContainsString($initialContent . $endingContent, $fileContents);
    }

    /**
     * @dataProvider dataProviderTestReplaceBetween
     */
    public function testReplaceBetween(string $initialContent, string $endingContent): void
    {
        $filesystem = new MemoryFilesystem();
        $filename = './.gitignore';
        $gitignoreWriter = new GitignoreWriter($filename, 'mysection', $filesystem);

        $filesystem->writeToFile($filename, $initialContent);
        $gitignoreWriter->updateSection(['robots.txt']);
        $filesystem->appendToFile($filename, $endingContent);

        // check file contents
        self::assertEquals(['robots.txt'], $gitignoreWriter->getEntries());
        $fileContents = (string)$filesystem->getFileContents($filename);
        self::assertStringContainsString($initialContent, $fileContents);
        self::assertStringContainsString($endingContent, $fileContents);

        // replace section
        $gitignoreWriter->replaceSection(['something.different.txt']);
        self::assertEquals(['something.different.txt'], $gitignoreWriter->getEntries());
        $fileContents = (string)$filesystem->getFileContents($filename);
        self::assertStringContainsString($initialContent, $fileContents);
        self::assertStringContainsString($endingContent, $fileContents);

        // remove section
        $gitignoreWriter->replaceSection();
        self::assertEquals([], $gitignoreWriter->getEntries());
        $fileContents = (string)$filesystem->getFileContents($filename);
        self::assertStringContainsString($initialContent . $endingContent, $fileContents);
    }

    /**
     * @return iterable
     * @psalm-return iterable<string, array{'initialContent': string, 'endingContent': string}>
     */
    public function dataProviderTestReplaceBetween(): iterable
    {
        yield 'empty' => [
            'initialContent' => '',
            'endingContent' => '',
        ];
        yield 'only-before' => [
            'initialContent' => 'Some random lines' . PHP_EOL . 'More lines',
            'endingContent' => '',
        ];
        yield 'only-after' => [
            'initialContent' => '',
            'endingContent' => 'Some random lines' . PHP_EOL . 'More lines',
        ];
        yield 'before-and-after' => [
            'initialContent' => 'Random content',
            'endingContent' => 'Some random lines' . PHP_EOL . 'More lines',
        ];
        yield 'only-before-newlines' => [
            'initialContent' => PHP_EOL . PHP_EOL . 'Some random lines' . PHP_EOL . 'More lines' . PHP_EOL . PHP_EOL,
            'endingContent' => '',
        ];
        yield 'only-after-newlines' => [
            'initialContent' => '',
            'endingContent' => PHP_EOL . PHP_EOL . 'Some random lines' . PHP_EOL . 'More lines' . PHP_EOL . PHP_EOL,
        ];
        yield 'before-and-after-newlines' => [
            'initialContent' => PHP_EOL . PHP_EOL . 'Random content' . PHP_EOL . PHP_EOL,
            'endingContent' => PHP_EOL . PHP_EOL . 'Some random lines' . PHP_EOL . 'More lines' . PHP_EOL . PHP_EOL,
        ];
    }

    public function testNoNewEntriesWithNoEntries(): void
    {
        $filesystem = new MemoryFilesystem();
        $filename = './.gitignore';
        $gitignoreWriter = new GitignoreWriter($filename, 'mysection', $filesystem);

        self::assertFalse($filesystem->isFile($filename));
        $gitignoreWriter->updateSection([]);
        self::assertEquals([], $gitignoreWriter->getEntries());

        $gitignoreWriter->replaceSection([]);
        self::assertEquals([], $gitignoreWriter->getEntries());
    }

    /**
     * @uses \Zawadi\GitignoreWriter\Filesystem
     */
    public function testConstructWithDefaultFilesystem(): void
    {
        $gitignoreWriter = new GitignoreWriter('not.important.txt', 'mysection');
        self::assertEquals([], $gitignoreWriter->getEntries());
    }

    public function testUniqueEntries(): void
    {
        $filesystem = new MemoryFilesystem();
        $filename = './.gitignore';
        $gitignoreWriter = new GitignoreWriter($filename, 'mysection', $filesystem);

        $gitignoreWriter->updateSection(['same.txt', 'other.txt', 'same.txt']);

        self::assertEquals(['other.txt', 'same.txt'], $gitignoreWriter->getEntries());
        self::assertEquals(
            1,
            substr_count((string)$filesystem->getFileContents($filename), 'other.txt')
        );
    }

    /**
     * @dataProvider dataProviderTestSectionNames
     */
    public function testSectionNames(string $sectionName): void
    {
        $filesystem = new MemoryFilesystem();
        $filename = './.gitignore';
        $gitignoreWriter = new GitignoreWriter($filename, $sectionName, $filesystem);
        $gitignoreWriter->updateSection(['irrelevant.txt']);

        self::assertEquals(['irrelevant.txt'], $gitignoreWriter->getEntries());

        $gitignoreWriter->updateSection(['more.txt']);
        self::assertEquals(['irrelevant.txt', 'more.txt'], $gitignoreWriter->getEntries());
    }

    /**
     * @return iterable
     * @psalm-return iterable<string, array{'sectionName': string}>
     */
    public function dataProviderTestSectionNames(): iterable
    {
        yield 'normal' => [
            'sectionName' => 'normal',
        ];
        yield 'single-quote' => [
            'sectionName' => 'single\'quote',
        ];
        yield 'double-quote' => [
            'sectionName' => 'double"quote',
        ];
        yield 'backslash' => [
            'sectionName' => 'slash\slash',
        ];
        yield 'slash' => [
            'sectionName' => 'slash/slash',
        ];
    }

    public function testDuplicateSections(): void
    {
        $filesystem = new MemoryFilesystem();
        $filename = './.gitignore';
        $filesystem->writeToFile($filename, 'Base contents' . PHP_EOL . 'containing 2 lines');
        $gitignoreWriter = new GitignoreWriter($filename, 'coolsection', $filesystem);

        // fill with initial values and get contents
        $gitignoreWriter->updateSection(['initial.txt']);
        $initialContents = (string) $filesystem->getFileContents($filename);

        // replace contents
        $gitignoreWriter->updateSection(['secondary.txt'], ['initial.txt']);
        $filesystem->appendToFile($filename, 'Random content' . PHP_EOL);
        $filesystem->appendToFile($filename, $initialContents);
        $filesystem->appendToFile($filename, PHP_EOL . 'More Random content' . PHP_EOL);

        // check that file now contains 2 sections
        self::assertEquals(
            2,
            substr_count(
                (string)$filesystem->getFileContents($filename),
                '###> coolsection >###'
            )
        );

        // check that only first section is read
        self::assertEquals(['secondary.txt'], $gitignoreWriter->getEntries());

        // remove section
        $gitignoreWriter->replaceSection();

        // check that initial values now pop up
        self::assertEquals(['initial.txt'], $gitignoreWriter->getEntries());
        self::assertEquals(
            1,
            substr_count(
                (string)$filesystem->getFileContents($filename),
                '###> coolsection >###'
            )
        );
    }
}