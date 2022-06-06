<?php

declare(strict_types=1);

namespace Zawadi\GitignoreWriter\Tests;

use PHPUnit\Framework\TestCase;
use Zawadi\GitignoreWriter\GitignoreWriter;
use Zawadi\GitignoreWriter\MemoryFilesystem;

/**
 * @covers \Zawadi\GitignoreWriter\GitignoreWriter
 * @uses \Zawadi\GitignoreWriter\FilesystemInterface
 * @uses \Zawadi\GitignoreWriter\MemoryFilesystem
 */
final class GitIgnoreWriterTest extends TestCase
{
    protected string $filename;
    protected MemoryFilesystem $filesystem;

    public function setUp(): void
    {
        $this->filename = tempnam(sys_get_temp_dir(), 'phpunit.gitignorewriter.test');
        $this->filesystem = new MemoryFilesystem();
    }

    public function tearDown(): void
    {
        unlink($this->filename);
    }

    public function testNoFile(): void
    {
        $gitignoreWriter = new GitignoreWriter($this->filename, 'mysection', $this->filesystem);

        // check new file contains no entries
        self::assertCount(0, $gitignoreWriter->getEntries());

        // add 2 entries and check they are stored
        $gitignoreWriter->updateSection(['/admin/', 'robots.txt']);
        self::assertEquals(['/admin/', 'robots.txt'], $gitignoreWriter->getEntries());
        self::assertTrue($this->filesystem->isFile($this->filename));

        // check contents of file
        $fileContents = (string)$this->filesystem->getFileContents($this->filename);
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
        $fileContents = (string)$this->filesystem->getFileContents($this->filename);
        self::assertStringNotContainsString('###> mysection >###', $fileContents);
        self::assertStringNotContainsString('/admin/', $fileContents);
        self::assertStringNotContainsString('robots.txt', $fileContents);
        self::assertStringNotContainsString('###< mysection <###', $fileContents);
    }

    public function testAppend(): void
    {
        $gitignoreWriter = new GitignoreWriter($this->filename, 'mysection', $this->filesystem);

        $initialContent = 'Some random lines' . PHP_EOL .
            'More lines';

        // put something random in
        $this->filesystem->writeToFile($this->filename, $initialContent);

        // add single entry
        $gitignoreWriter->updateSection(['robots.txt']);
        self::assertEquals(['robots.txt'], $gitignoreWriter->getEntries());
        // check original content is still there
        self::assertStringStartsWith(
            $initialContent . PHP_EOL,
            (string)$this->filesystem->getFileContents($this->filename),
        );

        // check removal
        $gitignoreWriter->updateSection([], ['robots.txt']);
        self::assertEquals([], $gitignoreWriter->getEntries());
        self::assertEquals($initialContent, $this->filesystem->getFileContents($this->filename));
    }

    public function testRemovalBetween(): void
    {
        $gitignoreWriter = new GitignoreWriter($this->filename, 'mysection', $this->filesystem);

        $initialContent = 'Some random lines' . PHP_EOL .
            'More lines' . PHP_EOL;
        $endingContent = PHP_EOL . 'More random lines' . PHP_EOL .
            'Something different' . PHP_EOL;

        $this->filesystem->writeToFile($this->filename, $initialContent);
        $gitignoreWriter->updateSection(['robots.txt']);
        $this->filesystem->appendToFile($this->filename, $endingContent);

        // check file contents
        self::assertEquals(['robots.txt'], $gitignoreWriter->getEntries());
        $fileContents = (string)$this->filesystem->getFileContents($this->filename);
        self::assertStringContainsString($initialContent, $fileContents);
        self::assertStringContainsString($endingContent, $fileContents);

        // remove section
        $gitignoreWriter->updateSection([], ['robots.txt']);
        self::assertEquals([], $gitignoreWriter->getEntries());
        $fileContents = (string)$this->filesystem->getFileContents($this->filename);
        self::assertStringContainsString($initialContent . $endingContent, $fileContents);
    }

    /**
     * @dataProvider dataProviderTestReplaceBetween
     */
    public function testReplaceBetween(string $initialContent, string $endingContent): void
    {
        $gitignoreWriter = new GitignoreWriter($this->filename, 'mysection', $this->filesystem);

        $this->filesystem->writeToFile($this->filename, $initialContent);
        $gitignoreWriter->updateSection(['robots.txt']);
        $this->filesystem->appendToFile($this->filename, $endingContent);

        // check file contents
        self::assertEquals(['robots.txt'], $gitignoreWriter->getEntries());
        $fileContents = (string)$this->filesystem->getFileContents($this->filename);
        self::assertStringContainsString($initialContent, $fileContents);
        self::assertStringContainsString($endingContent, $fileContents);

        // replace section
        $gitignoreWriter->replaceSection(['something.different.txt']);
        self::assertEquals(['something.different.txt'], $gitignoreWriter->getEntries());
        $fileContents = (string)$this->filesystem->getFileContents($this->filename);
        self::assertStringContainsString($initialContent, $fileContents);
        self::assertStringContainsString($endingContent, $fileContents);

        // remove section
        $gitignoreWriter->replaceSection();
        self::assertEquals([], $gitignoreWriter->getEntries());
        $fileContents = (string)$this->filesystem->getFileContents($this->filename);
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
        $gitignoreWriter = new GitignoreWriter($this->filename, 'mysection', $this->filesystem);

        self::assertFalse($this->filesystem->isFile($this->filename));
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
        $gitignoreWriter = new GitignoreWriter($this->filename, 'mysection', $this->filesystem);

        $gitignoreWriter->updateSection(['same.txt', 'other.txt', 'same.txt']);

        self::assertEquals(['other.txt', 'same.txt'], $gitignoreWriter->getEntries());
        self::assertEquals(
            1,
            substr_count((string)$this->filesystem->getFileContents($this->filename), 'other.txt'),
        );
    }

    /**
     * @dataProvider dataProviderTestSectionNames
     */
    public function testSectionNames(string $sectionName): void
    {
        $gitignoreWriter = new GitignoreWriter($this->filename, $sectionName, $this->filesystem);
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
        $this->filesystem->writeToFile($this->filename, 'Base contents' . PHP_EOL . 'containing 2 lines');
        $gitignoreWriter = new GitignoreWriter($this->filename, 'coolsection', $this->filesystem);

        // fill with initial values and get contents
        $gitignoreWriter->updateSection(['initial.txt']);
        $initialContents = (string)$this->filesystem->getFileContents($this->filename);

        // replace contents
        $gitignoreWriter->updateSection(['secondary.txt'], ['initial.txt']);
        $this->filesystem->appendToFile($this->filename, 'Random content' . PHP_EOL);
        $this->filesystem->appendToFile($this->filename, $initialContents);
        $this->filesystem->appendToFile($this->filename, PHP_EOL . 'More Random content' . PHP_EOL);

        // check that file now contains 2 sections
        self::assertEquals(
            2,
            substr_count(
                (string)$this->filesystem->getFileContents($this->filename),
                '###> coolsection >###',
            ),
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
                (string)$this->filesystem->getFileContents($this->filename),
                '###> coolsection >###',
            ),
        );
    }

    /**
     * @param list<string> $inputEntries
     * @param list<string> $expectedFoundEntries
     * @dataProvider dataProviderTestOrdering
     */
    public function testOrdering(array $inputEntries, array $expectedFoundEntries): void
    {
        $gitignoreWriter = new GitignoreWriter($this->filename, 'coolsection', $this->filesystem);
        $gitignoreWriter->updateSection($inputEntries);
        self::assertEquals($expectedFoundEntries, $gitignoreWriter->getEntries());
    }

    /**
     * @return iterable
     * @psalm-return iterable<string, array{'inputEntries': list<string>, 'expectedFoundEntries': list<string>}>
     */
    public function dataProviderTestOrdering(): iterable
    {
        yield 'normal' => [
            'inputEntries' => ['standard', 'before'],
            'expectedFoundEntries' => ['before', 'standard'],
        ];
        yield 'withinvert' => [
            'inputEntries' => ['!aaaa', 'standard', 'before'],
            'expectedFoundEntries' => ['before', 'standard', '!aaaa'],
        ];
        yield 'biggercollection' => [
            'inputEntries' => [
                'random',
                '!stan',
                'standard',
                '!bbbbb',
                'before',
                '!aaaa',
            ],
            'expectedFoundEntries' => [
                'before',
                'random',
                'standard',
                '!aaaa',
                '!bbbbb',
                '!stan',
            ],
        ];
    }
}
