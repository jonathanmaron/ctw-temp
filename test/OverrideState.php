<?php
declare(strict_types=1);

namespace CtwTest\Temp;

/**
 * Process-wide toggles consumed by the namespaced function overrides in
 * `test/_support/function_overrides.php`.
 *
 * Those overrides live in the `Ctw\Temp` namespace so that an unqualified
 * internal-function call such as `fopen(...)` inside {@see \Ctw\Temp\Temp} or
 * `function_exists(...)` inside {@see \Ctw\Temp\Posix} resolves to them at
 * runtime. This lets the suite exercise otherwise unreachable failure branches
 * — a missing POSIX extension and a file that cannot be created — without
 * modifying production code. Both toggles default to the pass-through value and
 * are restored after each test that flips them.
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
     * Restore every toggle to its pass-through default.
     */
    public static function reset(): void
    {
        self::$posixExtensionAvailable = true;
        self::$fopenReturnsFalse       = false;
    }
}
