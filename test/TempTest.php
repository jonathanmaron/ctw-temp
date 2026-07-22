<?php
declare(strict_types=1);

namespace CtwTest\Temp;

use Ctw\Temp\Exception\InvalidBasePathException;
use Ctw\Temp\Exception\InvalidPathSegmentException;
use Ctw\Temp\Exception\PathTraversalException;
use Ctw\Temp\Temp;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Unit tests for {@see Temp}.
 *
 * Every test uses a throwaway base path under the system temp directory so
 * the real `/var/tmp/php` tree is never created or modified. The base path
 * is removed in tearDown regardless of test outcome.
 */
#[CoversClass(Temp::class)]
final class TempTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = sprintf('%s%sctw-temp-%s', sys_get_temp_dir(), DIRECTORY_SEPARATOR, bin2hex(random_bytes(6)));
    }

    protected function tearDown(): void
    {
        $this->removeRecursive($this->basePath);
    }

    /**
     * Test that the assembled path is `<base>/<8-hex-hash>/<appId>` when the user/group segment is included.
     */
    public function testGetPathIncludesUserGroupHashSegmentByDefault(): void
    {
        $temp = new Temp('test.app', null, true, $this->basePath);

        $separator = preg_quote(DIRECTORY_SEPARATOR, '#');
        self::assertMatchesRegularExpression(
            sprintf('#^%s%s[0-9a-f]{8}%stest\.app$#', preg_quote($this->basePath, '#'), $separator, $separator),
            $temp->getPath(),
        );
    }

    /**
     * Test that the user/group hash segment is omitted when includeUserGroup is false.
     */
    public function testGetPathOmitsUserGroupHashSegmentWhenDisabled(): void
    {
        $temp = new Temp('test.app', null, false, $this->basePath);

        self::assertSame($this->basePath . DIRECTORY_SEPARATOR . 'test.app', $temp->getPath());
    }

    /**
     * Test that the optional second-level directory is appended to the path.
     */
    public function testGetPathAppendsLevel2SegmentWhenProvided(): void
    {
        $temp = new Temp('test.app', 'page-cache', false, $this->basePath);

        self::assertSame(
            $this->basePath . DIRECTORY_SEPARATOR . 'test.app' . DIRECTORY_SEPARATOR . 'page-cache',
            $temp->getPath(),
        );
    }

    /**
     * Test that a trailing slash on the base path is normalized away.
     */
    public function testGetPathNormalizesTrailingSlashOnBasePath(): void
    {
        $temp = new Temp('test.app', null, false, $this->basePath . DIRECTORY_SEPARATOR);

        self::assertSame($this->basePath . DIRECTORY_SEPARATOR . 'test.app', $temp->getPath());
    }

    /**
     * Test that unsafe characters are stripped from the appId segment.
     */
    public function testConstructorSanitizesUnsafeCharactersInAppId(): void
    {
        $temp = new Temp('My App!/x', null, false, $this->basePath);

        self::assertSame($this->basePath . DIRECTORY_SEPARATOR . 'MyAppx', $temp->getPath());
    }

    /**
     * Test that an empty appId is rejected.
     */
    public function testConstructorRejectsEmptyAppId(): void
    {
        $this->expectException(InvalidPathSegmentException::class);

        new Temp('', null, false, $this->basePath);
    }

    /**
     * Test that a traversal-only appId is rejected.
     */
    public function testConstructorRejectsTraversalAppId(): void
    {
        $this->expectException(InvalidPathSegmentException::class);

        new Temp('..', null, false, $this->basePath);
    }

    /**
     * Test that an empty base path is rejected.
     */
    public function testConstructorRejectsEmptyBasePath(): void
    {
        $this->expectException(InvalidBasePathException::class);

        new Temp('test.app', null, false, '');
    }

    /**
     * Test that createPath creates the directory, returns it, exists() reports true, and it is idempotent.
     */
    public function testCreatePathCreatesDirectoryAndIsIdempotent(): void
    {
        $temp = new Temp('test.app', null, false, $this->basePath);

        self::assertFalse($temp->existsPath());

        $created = $temp->createPath();

        self::assertSame($temp->getPath(), $created);
        self::assertDirectoryExists($created);
        self::assertTrue($temp->existsPath());
        self::assertSame($created, $temp->createPath());
    }

    /**
     * Test that createPath makes the shared base path world-writable but not the directories below it.
     */
    public function testCreatePathMakesOnlyTheBaseWorldWritable(): void
    {
        $temp = new Temp('test.app', null, false, $this->basePath);
        $temp->createPath();

        clearstatcache();

        self::assertSame(0777, fileperms($this->basePath) & 0777);
        self::assertSame(0, fileperms($temp->getPath()) & 0002);
    }

    /**
     * Test that deletePath removes the directory and its contents, then reports false when already gone.
     */
    public function testDeletePathRemovesDirectoryAndReturnsFalseWhenAbsent(): void
    {
        $temp = new Temp('test.app', null, false, $this->basePath);
        $temp->createPath();

        $file = $temp->createFile('data', 'txt');

        self::assertFileExists($file);
        self::assertTrue($temp->deletePath());
        self::assertDirectoryDoesNotExist($temp->getPath());
        self::assertFalse($temp->deletePath());
    }

    /**
     * Test that clear empties the directory contents but keeps the directory itself.
     */
    public function testClearEmptiesContentsButKeepsDirectory(): void
    {
        $temp = new Temp('test.app', null, false, $this->basePath);
        $file     = $temp->createFile('data', 'txt');

        self::assertFileExists($file);

        $temp->clearPath();

        self::assertDirectoryExists($temp->getPath());
        self::assertFileDoesNotExist($file);
    }

    /**
     * Test that create returns distinct, existing files with the requested extension inside the path.
     */
    public function testCreateReturnsUniqueExistingFilesWithExtension(): void
    {
        $temp = new Temp('test.app', null, false, $this->basePath);

        $first  = $temp->createFile('data', 'txt');
        $second = $temp->createFile('data', 'txt');

        self::assertNotSame($first, $second);
        self::assertFileExists($first);
        self::assertFileExists($second);
        self::assertStringStartsWith($temp->getPath() . DIRECTORY_SEPARATOR . 'data-', $first);
        self::assertStringEndsWith('.txt', $first);
    }

    /**
     * Test that create accepts an extension with a leading dot and normalizes it.
     */
    public function testCreateNormalizesExtensionWithLeadingDot(): void
    {
        $temp = new Temp('test.app', null, false, $this->basePath);

        $file = $temp->createFile('data', '.TXT');

        self::assertStringEndsWith('.txt', $file);
    }

    /**
     * Test that create produces no dot suffix when the extension is empty.
     */
    public function testCreateProducesNoSuffixWhenExtensionIsEmpty(): void
    {
        $temp = new Temp('test.app', null, false, $this->basePath);

        $file = $temp->createFile('data', '');

        self::assertStringNotContainsString('.', basename($file));
    }

    /**
     * Test that create sanitizes the filename stem.
     */
    public function testCreateSanitizesFilenameStem(): void
    {
        $temp = new Temp('test.app', null, false, $this->basePath);

        $file = $temp->createFile('my file!', 'log');

        self::assertStringStartsWith($temp->getPath() . DIRECTORY_SEPARATOR . 'myfile-', $file);
    }

    /**
     * Test that delete removes a file created inside the path and then reports false when it is gone.
     */
    public function testDeleteRemovesFileInsidePathAndReturnsFalseWhenAbsent(): void
    {
        $temp = new Temp('test.app', null, false, $this->basePath);
        $file     = $temp->createFile('data', 'tmp');

        self::assertFileExists($file);
        self::assertTrue($temp->deleteFile($file));
        self::assertFileDoesNotExist($file);
        self::assertFalse($temp->deleteFile($file));
    }

    /**
     * Test that delete refuses to remove a file located outside the temporary path.
     */
    public function testDeleteRefusesFileOutsideThePath(): void
    {
        $temp = new Temp('test.app', null, false, $this->basePath);
        $temp->createPath();

        $outside = (string) tempnam(sys_get_temp_dir(), 'ctw_outside_');

        try {
            $this->expectException(PathTraversalException::class);
            $temp->deleteFile($outside);
        } finally {
            @unlink($outside);
        }
    }

    /**
     * Recursively remove a directory and its contents; a no-op when it does not exist.
     */
    private function removeRecursive(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            assert($fileInfo instanceof SplFileInfo);
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());
            } else {
                unlink($fileInfo->getPathname());
            }
        }

        rmdir($path);
    }
}
