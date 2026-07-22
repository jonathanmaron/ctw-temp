<?php
declare(strict_types=1);

namespace Ctw\Temp;

use Ctw\Temp\Exception\PosixUnavailableException;

/**
 * Resolves the sanitized user and group of the current process via the POSIX extension.
 *
 * The returned values contain only lowercase alphanumeric characters, so they
 * are always safe to use as filename or path components. When a name cannot be
 * resolved, `noname` / `nogroup` are substituted, mirroring the behavior of the
 * original `TextControl\Polyfill` helper functions this class replaces.
 */
final class Posix
{
    /**
     * Substitute used when the current user name cannot be resolved.
     */
    private const string FALLBACK_USER = 'noname';

    /**
     * Substitute used when the current group name cannot be resolved.
     */
    private const string FALLBACK_GROUP = 'nogroup';

    /**
     * Return the sanitized current user and group as `<user>_<group>`.
     */
    #[\NoDiscard]
    public function currentUserGroup(): string
    {
        return sprintf('%s_%s', $this->currentUser(), $this->currentGroup());
    }

    /**
     * Return the sanitized username of the current process, or `noname` when unavailable.
     */
    #[\NoDiscard]
    public function currentUser(): string
    {
        $this->assertAvailable();

        $array = posix_getpwuid(posix_getuid());
        $name  = false === $array ? '' : $array['name'];

        return $this->sanitize('' === $name ? self::FALLBACK_USER : $name);
    }

    /**
     * Return the sanitized group name of the current process, or `nogroup` when unavailable.
     */
    #[\NoDiscard]
    public function currentGroup(): string
    {
        $this->assertAvailable();

        $array = posix_getgrgid(posix_getgid());
        $name  = false === $array ? '' : $array['name'];

        return $this->sanitize('' === $name ? self::FALLBACK_GROUP : $name);
    }

    /**
     * Ensure the POSIX extension is loaded before querying user/group data.
     *
     * @throws PosixUnavailableException When the `posix` extension is unavailable.
     */
    private function assertAvailable(): void
    {
        if (!function_exists('posix_getuid')) {
            $message = 'The "posix" extension is required to resolve the current user and group.';
            throw new PosixUnavailableException($message);
        }
    }

    /**
     * Normalize a POSIX name to lowercase alphanumerics so it is safe in a path.
     */
    private function sanitize(string $value): string
    {
        return (string) preg_replace('#[^a-z0-9]#', '', strtolower($value));
    }
}
