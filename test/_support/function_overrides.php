<?php
declare(strict_types=1);

namespace Ctw\Temp;

use CtwTest\Temp\OverrideState;

/*
 * Test-only overrides of the internal functions that {@see Temp} and
 * {@see Posix} call unqualified from within the `Ctw\Temp` namespace.
 *
 * PHP resolves an unqualified call like `is_writable(...)` to the current
 * namespace before falling back to the global function, so these definitions
 * shadow the global implementations for production code in `Ctw\Temp` only, and
 * only while the matching {@see OverrideState} toggle diverges from its
 * pass-through default. Each override otherwise delegates to the global
 * function, leaving normal test runs untouched.
 *
 * Each override reproduces the exact parameter and return types the production
 * code relies on, so PHPStan's analysis of that code is unaffected. Where a
 * global accepts further optional arguments the production code never passes —
 * for example fopen()'s $use_include_path and $context — those are omitted.
 *
 * This file defines functions and therefore cannot be autoloaded; it is loaded
 * once from the PHPUnit bootstrap (`test/bootstrap.php`).
 */

/**
 * Reports `posix_getuid` as undefined while {@see OverrideState::$posixExtensionAvailable} is false.
 *
 * @see \function_exists()
 */
function function_exists(string $function): bool
{
    if (!OverrideState::$posixExtensionAvailable && 'posix_getuid' === $function) {
        return false;
    }

    return \function_exists($function);
}

/**
 * Fails without touching the filesystem while {@see OverrideState::$fopenReturnsFalse} is true.
 *
 * @see \fopen()
 *
 * @return false|resource
 */
function fopen(string $filename, string $mode)
{
    if (OverrideState::$fopenReturnsFalse) {
        return false;
    }

    return \fopen($filename, $mode);
}

/**
 * Reports the target as not writable while {@see OverrideState::$isWritableReturnsFalse} is true.
 *
 * @see \is_writable()
 */
function is_writable(string $filename): bool
{
    if (OverrideState::$isWritableReturnsFalse) {
        return false;
    }

    return \is_writable($filename);
}

/**
 * Returns the configured {@see OverrideState::$posixUserRecord} when it is not null.
 *
 * @see \posix_getpwuid()
 *
 * @return array{name: string, ...}|false
 */
function posix_getpwuid(int $userId): array|false
{
    $record = OverrideState::$posixUserRecord;
    if (null !== $record) {
        return $record;
    }

    return \posix_getpwuid($userId);
}

/**
 * Returns the configured {@see OverrideState::$posixGroupRecord} when it is not null.
 *
 * @see \posix_getgrgid()
 *
 * @return array{name: string, ...}|false
 */
function posix_getgrgid(int $groupId): array|false
{
    $record = OverrideState::$posixGroupRecord;
    if (null !== $record) {
        return $record;
    }

    return \posix_getgrgid($groupId);
}
