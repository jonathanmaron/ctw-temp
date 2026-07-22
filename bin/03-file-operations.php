<?php
declare(strict_types=1);

/*
 * Demo 03 — File operations
 * =========================
 *
 *     createFile($name, $ext) -> atomically create a uniquely-named empty file
 *                                and return its absolute path. Two calls with
 *                                the same name never collide.
 *     deleteFile($path)       -> delete a file, but ONLY if it lives inside the
 *                                temporary directory (a path-traversal guard).
 *
 * Uses a throwaway base path and cleans up after itself.
 *
 * Run it with:
 *     php bin/03-file-operations.php
 */

use Ctw\Temp\Exception\PathTraversalException;
use Ctw\Temp\Temp;

require __DIR__ . '/autoload.php';

$demoBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ctw-temp-demo';
$temp     = new Temp('demo.app', null, false, $demoBase);

// createFile() creates the directory first (if needed), then an empty file with
// a unique name of the form  <name>-<random hex>.<ext>. Because the random part
// differs every time, the same name+extension yields two different files.
$first  = $temp->createFile('report', 'pdf');
$second = $temp->createFile('report', 'pdf');
echo "Two files from the same name+extension are still unique:\n";
echo '   ' . $first . "\n";
echo '   ' . $second . "\n";
echo 'Both exist on disk: ' . var_export(is_file($first) && is_file($second), true) . "\n\n";

// The extension is optional and normalized (a leading dot is allowed, and it is
// lower-cased). An empty extension produces a filename with no dot suffix.
$noExtension = $temp->createFile('data', '');
echo "An empty extension produces a file with no dot suffix:\n";
echo '   ' . $noExtension . "\n\n";

// deleteFile() removes a file that lives inside the temporary directory.
$temp->deleteFile($first);
echo 'After deleteFile($first), it still exists: ' . var_export(is_file($first), true) . "\n\n";

// SAFETY: deleteFile() refuses to touch anything OUTSIDE the temporary
// directory, so a crafted path cannot be used to delete arbitrary files. Here
// we point it at a file in the system temp dir and catch the guard.
$outside = (string) tempnam(sys_get_temp_dir(), 'outside_');
echo "Trying to deleteFile() a path outside the temp directory:\n";

try {
    $temp->deleteFile($outside);
} catch (PathTraversalException $pathTraversalException) {
    echo '   Blocked, as expected: ' . $pathTraversalException->getMessage() . "\n";
}

// Clean up everything the demo created.
if (is_file($outside)) {
    unlink($outside);
}

$temp->deletePath();

if (is_dir($demoBase)) {
    rmdir($demoBase);
}
