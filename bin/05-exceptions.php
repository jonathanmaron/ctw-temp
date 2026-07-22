<?php
declare(strict_types=1);

/*
 * Demo 05 — Exceptions
 * ====================
 *
 * Every failure throws a specific exception, and they all implement
 * `Ctw\Temp\Exception\ExceptionInterface`, so you can catch them individually or
 * all at once. This demo triggers the ones that are easy to reproduce safely.
 *
 * Run it with:
 *     php bin/05-exceptions.php
 */

use Ctw\Temp\Exception\DirectoryNotCreatedException;
use Ctw\Temp\Exception\ExceptionInterface;
use Ctw\Temp\Exception\InvalidBasePathException;
use Ctw\Temp\Exception\InvalidPathSegmentException;
use Ctw\Temp\Exception\PathTraversalException;
use Ctw\Temp\Temp;

require __DIR__ . '/autoload.php';

// 1) InvalidBasePathException — the base path is empty.
try {
    new Temp('demo.app', null, false, '');
} catch (InvalidBasePathException $invalidBasePathException) {
    echo '1) InvalidBasePathException:    ' . $invalidBasePathException->getMessage() . "\n";
}

// 2) InvalidPathSegmentException — the appId is empty or unsafe after
//    sanitization. '..' is a path-traversal segment, so it is rejected.
try {
    new Temp('..', null, false, sys_get_temp_dir());
} catch (InvalidPathSegmentException $invalidPathSegmentException) {
    echo '2) InvalidPathSegmentException: ' . $invalidPathSegmentException->getMessage() . "\n";
}

// 3) DirectoryNotCreatedException — the directory cannot be created because its
//    parent is a *file*, not a directory. We create a temp file, then ask Temp
//    to build its tree *inside* that file, which mkdir() cannot do.
$file = (string) tempnam(sys_get_temp_dir(), 'ctw_file_');

try {
    new Temp('demo.app', null, false, $file . DIRECTORY_SEPARATOR . 'sub')->createPath();
} catch (DirectoryNotCreatedException $directoryNotCreatedException) {
    echo '3) DirectoryNotCreatedException: ' . $directoryNotCreatedException->getMessage() . "\n";
} finally {
    if (is_file($file)) {
        unlink($file);
    }
}

// 4) PathTraversalException — deleteFile() refuses a path outside the temp dir.
//    The directory must exist for the guard to run, so we create it first.
$demoBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ctw-temp-demo';
$temp     = new Temp('demo.app', null, false, $demoBase);
$outside  = (string) tempnam(sys_get_temp_dir(), 'ctw_outside_');
$temp->createPath();

try {
    $temp->deleteFile($outside);
} catch (PathTraversalException $pathTraversalException) {
    echo '4) PathTraversalException:      ' . $pathTraversalException->getMessage() . "\n";
} finally {
    if (is_file($outside)) {
        unlink($outside);
    }

    $temp->deletePath();

    if (is_dir($demoBase)) {
        rmdir($demoBase);
    }
}

echo "\n";

// 5) Catch-all: because every package exception implements ExceptionInterface,
//    a single catch handles any failure originating from Temp.
try {
    new Temp('demo.app', null, false, '');
} catch (ExceptionInterface $exception) {
    echo '5) Caught via ExceptionInterface: ' . $exception::class . "\n";
}

// Not triggered here (they need an unusual filesystem state that is awkward to
// set up safely in a demo), but they exist for completeness:
//   - DirectoryNotWritableException: the directory exists but is read-only.
//   - FileNotCreatedException:        the directory rejects new files.
//   - PosixUnavailableException:      the "posix" extension is not loaded.
