<?php
declare(strict_types=1);

namespace Ctw\Temp;

use Ctw\Temp\Exception\DirectoryNotCreatedException;
use Ctw\Temp\Exception\DirectoryNotWritableException;
use Ctw\Temp\Exception\FileNotCreatedException;
use Ctw\Temp\Exception\InvalidBasePathException;
use Ctw\Temp\Exception\InvalidPathSegmentException;
use Ctw\Temp\Exception\PathTraversalException;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Generator for per-user, per-application temporary directories and files on disk.
 *
 * Builds a temporary path of the form:
 *
 *     <basePath>[/<hash>]/<appId>[/<level2>]
 *
 * for example `/var/tmp/php/78b43994/www.example.com/page-cache`, and
 * provides helpers to create/delete the directory and unique files within it.
 *
 * The base path defaults to `/var/tmp/php` (real disk) rather than
 * `sys_get_temp_dir()`, which on Debian 14+ is a RAM-backed `tmpfs` that fills
 * up quickly. The optional `<hash>` segment isolates one user/group's files
 * from another's and is derived, via the POSIX extension, from the user and
 * group running the current process.
 *
 * For clarity the methods are grouped into two clearly marked sections below:
 * directory operations and file operations.
 *
 * The class has zero package dependencies. Creating and defining the
 * application temp-path constant (e.g. `APP_PATH_TEMP`) remains the calling
 * application's responsibility.
 */
final readonly class Temp
{
    /**
     * Default base path: real disk storage, not the `tmpfs`-backed `sys_get_temp_dir()`.
     */
    public const string DEFAULT_BASE_PATH = '/var/tmp/php';

    /**
     * Hash algorithm for the user/group segment (8 lowercase hex characters).
     */
    private const string HASH_ALGORITHM = 'crc32b';

    /**
     * Creation mode for the shared base path: world-writable (0777), like `/tmp`.
     *
     * Every user creates its own subtree beneath the base, so the base itself
     * must be writable by all. Applied only to the base path, and forced with an
     * explicit `chmod()` so it survives a restrictive umask.
     */
    private const int BASE_PATH_MODE = 0777;

    /**
     * Creation mode (umask-adjusted) for the per-user directories below the base.
     *
     * Kept out of `0777` on purpose: only the shared base is world-writable, the
     * directories inside it are not.
     */
    private const int DEFAULT_PATH_MODE = 0755;

    /**
     * Upper bound on unique-filename attempts before giving up in {@see self::createFile()}.
     */
    private const int MAX_CREATE_ATTEMPTS = 100;

    /**
     * The normalized base path (the shared, world-writable mount point).
     */
    private string $basePath;

    /**
     * The fully assembled, sanitized temporary path.
     */
    private string $path;

    /**
     * @param string      $appId Required application identifier or hostname (e.g. `hostname_www()`).
     * @param null|string $level2 Optional second-level directory, e.g. `page-cache`.
     * @param bool        $includeUserGroup Whether to include the per-user/group `<hash>` segment.
     * @param string      $basePath Base path holding the temporary tree.
     * @param int         $pathMode Creation mode for the per-user directories (umask-adjusted).
     * @param Posix       $posix Resolver for the current user/group `<hash>` segment.
     */
    public function __construct(
        string $appId,
        ?string $level2 = null,
        bool $includeUserGroup = true,
        string $basePath = self::DEFAULT_BASE_PATH,
        private int $pathMode = self::DEFAULT_PATH_MODE,
        private Posix $posix = new Posix(),
    ) {
        $this->basePath = $this->normalizeBasePath($basePath);
        $this->path     = $this->buildPath($appId, $level2, $includeUserGroup);
    }

    /**
     * Return the fully assembled temporary path (the directory is not created by this call).
     */
    #[\NoDiscard]
    public function getPath(): string
    {
        return $this->path;
    }

    // -------------------------------------------------------------------------
    // Directory operations
    // -------------------------------------------------------------------------

    /**
     * Create the temporary path recursively and return it.
     *
     * The shared base path is ensured first as world-writable (0777); the
     * per-user directories created below it use {@see self::$pathMode}.
     *
     * @throws DirectoryNotCreatedException When a directory in the path cannot be created.
     * @throws DirectoryNotWritableException When the temporary directory exists but is not writable.
     */
    public function createPath(): string
    {
        $this->createBasePath();

        if (!is_dir($this->path) && !@mkdir($this->path, $this->pathMode, true) && !is_dir($this->path)) {
            $format  = 'Unable to create the temporary directory "%s".';
            $message = sprintf($format, $this->path);
            throw new DirectoryNotCreatedException($message);
        }

        if (!is_writable($this->path)) {
            $format  = 'The temporary directory "%s" is not writable.';
            $message = sprintf($format, $this->path);
            throw new DirectoryNotWritableException($message);
        }

        return $this->path;
    }

    /**
     * Recursively delete the temporary path and everything inside it.
     *
     * @return bool True when the path existed and was removed, false when it did not exist.
     */
    public function deletePath(): bool
    {
        if (!is_dir($this->path)) {
            return false;
        }

        $this->clearPath();

        return rmdir($this->path);
    }

    /**
     * Whether the temporary directory currently exists on disk.
     */
    #[\NoDiscard]
    public function existsPath(): bool
    {
        return is_dir($this->path);
    }

    /**
     * Remove every file and sub-directory inside the temporary directory, keeping the directory itself.
     */
    public function clearPath(): void
    {
        if (!is_dir($this->path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->path, FilesystemIterator::SKIP_DOTS),
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
    }

    /**
     * Ensure the shared base path exists and is world-writable (0777).
     *
     * The base path is the common mount point beneath which every user
     * creates its own subtree, so — like `/tmp` — it must be writable by all.
     * Only the base itself is made 0777 (forced with `chmod()` to survive the
     * umask); the per-user directories created below it keep {@see self::$pathMode}.
     *
     * @throws DirectoryNotCreatedException When the base path cannot be created.
     */
    private function createBasePath(): void
    {
        if (is_dir($this->basePath)) {
            return;
        }

        if (!@mkdir($this->basePath, self::BASE_PATH_MODE, true) && !is_dir($this->basePath)) {
            $format  = 'Unable to create the base path "%s".';
            $message = sprintf($format, $this->basePath);
            throw new DirectoryNotCreatedException($message);
        }

        @chmod($this->basePath, self::BASE_PATH_MODE);
    }

    // -------------------------------------------------------------------------
    // File operations
    // -------------------------------------------------------------------------

    /**
     * Atomically create a uniquely named, empty file inside the temporary directory.
     *
     * The directory is created first if it does not yet exist. The filename is
     * `<name>-<random>.<extension>`; an exclusive create guarantees no two
     * callers ever receive the same file.
     *
     * @param string $name Human-readable filename stem (sanitized to `[A-Za-z0-9._-]`).
     * @param string $extension File extension, with or without a leading dot (may be empty).
     * @return string Absolute path to the newly created file.
     * @throws FileNotCreatedException When no unique file could be created.
     */
    #[\NoDiscard]
    public function createFile(string $name, string $extension): string
    {
        $this->createPath();

        $name   = $this->sanitizeFilename($name);
        $suffix = $this->buildExtensionSuffix($extension);

        for ($attempt = 0; self::MAX_CREATE_ATTEMPTS > $attempt; ++$attempt) {
            $file   = sprintf('%s%s%s-%s%s', $this->path, DIRECTORY_SEPARATOR, $name, $this->uniqueToken(), $suffix);
            $handle = @fopen($file, 'x');
            if (false !== $handle) {
                fclose($handle);

                return $file;
            }
        }

        $format  = 'Unable to create a unique file in "%s".';
        $message = sprintf($format, $this->path);
        throw new FileNotCreatedException($message);
    }

    /**
     * Delete a file previously created inside the temporary directory.
     *
     * Refuses to delete anything resolving outside the temporary directory, so a
     * crafted argument cannot be used to remove arbitrary files.
     *
     * @return bool True when the file existed and was deleted, false otherwise.
     * @throws PathTraversalException When the resolved file lies outside the temporary directory.
     */
    public function deleteFile(string $file): bool
    {
        $real = realpath($file);
        if (false === $real) {
            return false;
        }

        $base = realpath($this->path);
        if (false === $base) {
            return false;
        }

        if (!str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
            $format  = 'Refusing to delete "%s": it is outside the temporary directory "%s".';
            $message = sprintf($format, $file, $this->path);
            throw new PathTraversalException($message);
        }

        if (!is_file($real)) {
            return false;
        }

        return unlink($real);
    }

    /**
     * Sanitize the filename stem, falling back to `file` when nothing usable remains.
     */
    private function sanitizeFilename(string $name): string
    {
        $clean = (string) preg_replace('#[^A-Za-z0-9._-]#', '', $name);

        return '' === $clean ? 'file' : $clean;
    }

    /**
     * Build the `.ext` suffix for a filename, or an empty string when no extension is given.
     */
    private function buildExtensionSuffix(string $extension): string
    {
        $clean = strtolower((string) preg_replace('#[^A-Za-z0-9]#', '', ltrim($extension, '.')));

        return '' === $clean ? '' : '.' . $clean;
    }

    /**
     * Return a random, collision-resistant token for unique filenames.
     */
    private function uniqueToken(): string
    {
        return bin2hex(random_bytes(8));
    }

    // -------------------------------------------------------------------------
    // Path construction
    // -------------------------------------------------------------------------

    /**
     * Normalize the base path, stripping trailing slashes and rejecting an empty value.
     *
     * @throws InvalidBasePathException When the base path is empty.
     */
    private function normalizeBasePath(string $basePath): string
    {
        $base = rtrim($basePath, DIRECTORY_SEPARATOR);
        if ('' === $base) {
            $message = 'The base path must not be empty.';
            throw new InvalidBasePathException($message);
        }

        return $base;
    }

    /**
     * Assemble and sanitize the full temporary path from its configured segments.
     */
    private function buildPath(string $appId, ?string $level2, bool $includeUserGroup): string
    {
        $segments = [$this->basePath];

        if ($includeUserGroup) {
            $segments[] = hash(self::HASH_ALGORITHM, $this->posix->currentUserGroup());
        }

        $segments[] = $this->sanitizeSegment($appId, 'appId');

        if (null !== $level2) {
            $segments[] = $this->sanitizeSegment($level2, 'level2');
        }

        return implode(DIRECTORY_SEPARATOR, $segments);
    }

    /**
     * Sanitize a single path segment, rejecting empty or traversal values.
     *
     * @throws InvalidPathSegmentException When the segment is empty or unsafe after sanitization.
     */
    private function sanitizeSegment(string $segment, string $label): string
    {
        $clean = (string) preg_replace('#[^A-Za-z0-9._-]#', '', $segment);

        if ('' === $clean || '.' === $clean || '..' === $clean) {
            $format  = 'The "%s" path segment "%s" is empty or unsafe after sanitization.';
            $message = sprintf($format, $label, $segment);
            throw new InvalidPathSegmentException($message);
        }

        return $clean;
    }
}
