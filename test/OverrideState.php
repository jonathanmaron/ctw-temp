<?php
declare(strict_types=1);

namespace CtwTest\Temp;

/**
 * Process-wide toggles consumed by the namespaced function overrides in
 * `test/_support/function_overrides.php`.
 *
 * Those overrides live in the `Ctw\Temp` namespace so that an unqualified
 * internal-function call such as `is_writable(...)` inside {@see \Ctw\Temp\Temp}
 * or `posix_getpwuid(...)` inside {@see \Ctw\Temp\Posix} resolves to them at
 * runtime. This lets the suite exercise otherwise unreachable failure and
 * fallback branches without modifying production code. Every toggle defaults to
 * the pass-through value; {@see AbstractTestCase} restores them before and after
 * each test.
 */
final class OverrideState
{
    /**
     * When false, `Ctw\Temp\function_exists('posix_getuid')` reports the POSIX extension as absent.
     */
    public static bool $posixExtensionAvailable = true;

    /**
     * When true, `Ctw\Temp\fopen()` fails without touching the filesystem.
     */
    public static bool $fopenReturnsFalse = false;

    /**
     * When true, `Ctw\Temp\is_writable()` reports its target as not writable.
     */
    public static bool $isWritableReturnsFalse = false;

    /**
     * Overrides the return of `Ctw\Temp\posix_getpwuid()`: null delegates to the
     * real function, false simulates a failed lookup, and an array supplies a record.
     *
     * @var null|array{name: string, ...}|false
     */
    public static array|bool|null $posixUserRecord = null;

    /**
     * Overrides the return of `Ctw\Temp\posix_getgrgid()`: null delegates to the
     * real function, false simulates a failed lookup, and an array supplies a record.
     *
     * @var null|array{name: string, ...}|false
     */
    public static array|bool|null $posixGroupRecord = null;

    /**
     * Restore every toggle to its pass-through default.
     */
    public static function reset(): void
    {
        self::$posixExtensionAvailable = true;
        self::$fopenReturnsFalse       = false;
        self::$isWritableReturnsFalse  = false;
        self::$posixUserRecord         = null;
        self::$posixGroupRecord        = null;
    }
}
