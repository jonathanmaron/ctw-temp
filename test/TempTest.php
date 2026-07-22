<?php
declare(strict_types=1);

namespace CtwTest\Temp;

use Ctw\Temp\Exception\DirectoryNotCreatedException;
use Ctw\Temp\Exception\DirectoryNotWritableException;
use Ctw\Temp\Exception\FileNotCreatedException;
use Ctw\Temp\Exception\InvalidBasePathException;
use Ctw\Temp\Exception\InvalidPathSegmentException;
use Ctw\Temp\Exception\PathTraversalException;
use Ctw\Temp\Temp;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversClass;
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
final class TempTest extends AbstractTestCase
{
    /**
     * Throwaway base path for the current test, created in setUp and removed in tearDown.
     */
    private string $basePath;

    /**
     * Assigns a unique throwaway base path under the system temp directory before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath = sprintf('%s%sctw-temp-%s', sys_get_temp_dir(), DIRECTORY_SEPARATOR, bin2hex(random_bytes(6)));
    }

    /**
     * Removes the throwaway base path and everything beneath it after each test.
     */
    protected function tearDown(): void
    {
        $this->removeRecursive($this->basePath);

        parent::tearDown();
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
     * Test that createPath throws when a file already occupies the target path.
     *
     * @throws DirectoryNotCreatedException When the directory cannot be created because a file is in the way.
     */
    public function testCreatePathThrowsWhenTargetPathIsOccupiedByAFile(): void
    {
        $temp = new Temp('test.app', null, false, $this->basePath);
        mkdir($this->basePath, 0777, true);
        touch($this->basePath . DIRECTORY_SEPARATOR . 'test.app');

        $this->expectException(DirectoryNotCreatedException::class);

        $temp->createPath();
    }

    /**
     * Test that createPath throws when the target directory is not writable.
     *
     * The writability check is forced via {@see OverrideState} rather than real
     * permission bits, so the branch is exercised deterministically even when the
     * suite runs as root (where a mode-0555 directory would still report writable).
     *
     * @throws DirectoryNotWritableException When the directory reports as not writable.
     */
    public function testCreatePathThrowsWhenExistingDirectoryIsNotWritable(): void
    {
        $temp = new Temp('test.app', null, false, $this->basePath);

        OverrideState::$isWritableReturnsFalse = true;

        $this->expectException(DirectoryNotWritableException::class);

        $temp->createPath();
    }

    /**
     * Test that createPath throws when the shared base path itself cannot be created.
     *
     * @throws DirectoryNotCreatedException When the base path cannot be created because a file blocks it.
     */
    public function testCreatePathThrowsWhenBasePathCannotBeCreated(): void
    {
        $blocker = (string) tempnam(sys_get_temp_dir(), 'ctw_blocker_');
        $temp    = new Temp('test.app', null, false, $blocker . DIRECTORY_SEPARATOR . 'nested');

        try {
            $this->expectException(DirectoryNotCreatedException::class);
            $temp->createPath();
        } finally {
            @unlink($blocker);
        }
    }

    /**
     * Test that clearPath is a no-op when the temporary directory does not exist.
     */
    public function testClearPathIsANoOpWhenDirectoryDoesNotExist(): void
    {
        $temp = new Temp('test.app', null, false, $this->basePath);

        $temp->clearPath();

        self::assertFalse($temp->existsPath());
    }

    /**
     * Test that clearPath removes nested sub-directories as well as files.
     */
    public function testClearPathRemovesNestedSubdirectories(): void
    {
        $temp = new Temp('test.app', null, false, $this->basePath);
        $temp->createPath();

        $nested = $temp->getPath() . DIRECTORY_SEPARATOR . 'nested';
        mkdir($nested, 0777, true);
        touch($nested . DIRECTORY_SEPARATOR . 'inner.txt');

        $temp->clearPath();

        self::assertDirectoryExists($temp->getPath());
        self::assertDirectoryDoesNotExist($nested);
    }

    /**
     * Test that createFile throws once every unique-name attempt has been exhausted.
     *
     * @throws FileNotCreatedException Once every creation attempt is forced to fail.
     */
    public function testCreateFileThrowsWhenNoUniqueFileCanBeCreated(): void
    {
        $temp = new Temp('test.app', null, false, $this->basePath);

        OverrideState::$fopenReturnsFalse = true;

        $this->expectException(FileNotCreatedException::class);

        (void) $temp->createFile('data', 'txt');
    }

    /**
     * Test that createFile falls back to the `file` stem when the name strips to nothing.
     */
    public function testCreateFileFallsBackToFileStemWhenNameIsFullyStripped(): void
    {
        $temp = new Temp('test.app', null, false, $this->basePath);

        $file = $temp->createFile('!!!@@@', 'txt');

        self::assertStringStartsWith($temp->getPath() . DIRECTORY_SEPARATOR . 'file-', $file);
    }

    /**
     * Test that deleteFile returns false when the temporary directory does not exist.
     */
    public function testDeleteFileReturnsFalseWhenTemporaryPathDoesNotExist(): void
    {
        $temp = new Temp('test.app', null, false, $this->basePath);

        $outside = (string) tempnam(sys_get_temp_dir(), 'ctw_exists_');

        try {
            self::assertFalse($temp->deleteFile($outside));
        } finally {
            @unlink($outside);
        }
    }

    /**
     * Test that deleteFile returns false when the target resolves to a directory rather than a file.
     */
    public function testDeleteFileReturnsFalseWhenTargetResolvesToADirectory(): void
    {
        $temp = new Temp('test.app', null, false, $this->basePath);
        $temp->createPath();

        $subDirectory = $temp->getPath() . DIRECTORY_SEPARATOR . 'a-directory';
        mkdir($subDirectory, 0777, true);

        self::assertFalse($temp->deleteFile($subDirectory));
        self::assertDirectoryExists($subDirectory);
    }

    /**
     * Test that an unsafe optional second-level segment is rejected by the constructor.
     *
     * @throws InvalidPathSegmentException When the level2 segment is empty or unsafe after sanitization.
     */
    public function testConstructorRejectsUnsafeLevel2Segment(): void
    {
        $this->expectException(InvalidPathSegmentException::class);

        new Temp('test.app', '..', false, $this->basePath);
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
