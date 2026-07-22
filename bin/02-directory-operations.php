<?php
declare(strict_types=1);

/*
 * Demo 02 — Directory lifecycle
 * =============================
 *
 *     createPath()  -> create the temporary directory tree (recursively)
 *     existsPath()  -> does the directory exist yet?
 *     clearPath()   -> empty the directory but keep the directory itself
 *     deletePath()  -> remove the directory and everything inside it
 *
 * We use a throwaway base path under the system temp directory, so this demo is
 * fully self-contained and cleans up after itself — it never touches the real
 * /var/tmp/php tree.
 *
 * Run it with:
 *     php bin/02-directory-operations.php
 */

use Ctw\Temp\Temp;

require __DIR__ . '/autoload.php';

// A demo-only base path, e.g. /tmp/ctw-temp-demo. Everything happens under here.
$demoBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ctw-temp-demo';

// includeUserGroup = false keeps the printed paths short and predictable.
$temp = new Temp('demo.app', null, false, $demoBase);
echo 'Working directory: ' . $temp->getPath() . "\n\n";

// getPath() did not create anything, so the directory does not exist yet.
echo 'existsPath() before createPath(): ' . var_export($temp->existsPath(), true) . "\n";

// createPath() creates the whole tree recursively and returns the path.
//
// Permission model: the shared base (here /tmp/ctw-temp-demo) is made
// world-writable (0777) so any OS user can create its own subtree beneath it;
// the per-user directory created below it is 0755.
$created = $temp->createPath();
echo 'createPath() returned:            ' . $created . "\n";
echo 'existsPath() after createPath():  ' . var_export($temp->existsPath(), true) . "\n\n";

// Put two files in the directory so we can watch clearPath() empty it.
$fileA = $temp->createFile('example', 'txt');
$fileB = $temp->createFile('example', 'txt');
echo "Created two files:\n";
echo '   ' . $fileA . "\n";
echo '   ' . $fileB . "\n";
echo 'Both files exist:                 ' . var_export(is_file($fileA) && is_file($fileB), true) . "\n\n";

// clearPath() removes the *contents* but keeps the directory itself.
$temp->clearPath();
echo 'Files exist after clearPath():    ' . var_export(is_file($fileA) || is_file($fileB), true) . "\n";
echo 'existsPath() after clearPath():   ' . var_export($temp->existsPath(), true) . "\n\n";

// deletePath() removes the directory and everything still inside it.
$temp->deletePath();
echo 'existsPath() after deletePath():  ' . var_export($temp->existsPath(), true) . "\n";

// deletePath() removed our per-app subtree; tidy the shared demo base too.
if (is_dir($demoBase)) {
    rmdir($demoBase);
}
