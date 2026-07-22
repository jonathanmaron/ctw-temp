<?php
declare(strict_types=1);

namespace Ctw\Temp;

use CtwTest\Temp\OverrideState;

/*
 * Test-only overrides of the internal functions that {@see Temp} and
 * {@see Posix} call unqualified from within the `Ctw\Temp` namespace.
 *
 * PHP resolves an unqualified call like `fopen(...)` to the current namespace
 * before falling back to the global function, so these definitions shadow the
 * global implementations for production code in `Ctw\Temp` only, and only while
 * the matching {@see OverrideState} toggle is set. Each override otherwise
 * delegates to the global function, leaving normal test runs untouched. Their
 * signatures and return types mirror the globals exactly so static analysis of
 * the production code is unaffected.
 *
 * This file defines functions and therefore cannot be autoloaded; it is pulled
 * in with `require_once` from the test cases that rely on it.
 */

/**
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
 * @see \fopen()
 *
 * @return resource|false
 */
function fopen(string $filename, string $mode)
{
    if (OverrideState::$fopenReturnsFalse) {
        return false;
    }

    return \fopen($filename, $mode);
}
