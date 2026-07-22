<?php
declare(strict_types=1);

/*
 * Demo 01 — The path model (no disk I/O)
 * ======================================
 *
 * `getPath()` only *computes* the temporary path string from the constructor
 * arguments — it never touches the filesystem. That makes it the perfect place
 * to see how each constructor argument maps to a segment of the final path:
 *
 *     <basePath>[/<hash>]/<appId>[/<level2>]
 *
 * Run it with:
 *     php bin/01-path-model.php
 */

use Ctw\Temp\Temp;

require __DIR__ . '/autoload.php';

// 1) The simplest possible instance: only the required $appId is given.
//    - $basePath defaults to /var/tmp/php
//    - the per-user/group <hash> segment is included by default
//    - there is no optional <level2> segment
$default = new Temp('www.example.com');
echo "1) Defaults (appId only):\n";
echo '   ' . $default->getPath() . "\n\n";

// 2) Add an optional second-level directory (2nd argument). Handy for grouping,
//    e.g. a page cache that you can clear independently.
$withLevel2 = new Temp('www.example.com', 'page-cache');
echo "2) With a level2 directory ('page-cache'):\n";
echo '   ' . $withLevel2->getPath() . "\n\n";

// 3) Turn OFF the per-user/group <hash> segment (3rd argument = false). Use this
//    when every process should share one directory instead of one per user.
$noHash = new Temp('www.example.com', null, false);
echo "3) Without the <hash> segment (includeUserGroup = false):\n";
echo '   ' . $noHash->getPath() . "\n\n";

// 4) Override the base path (4th argument). The default /var/tmp/php lives on
//    real disk; you can point the tree anywhere you like.
$customBase = new Temp('www.example.com', 'cache', false, '/srv/tmp');
echo "4) Custom base path ('/srv/tmp'):\n";
echo '   ' . $customBase->getPath() . "\n\n";

// 5) Unsafe characters in appId/level2 are stripped so each value is always a
//    single, safe path segment. 'My App!/v2' collapses to 'MyAppv2'.
$sanitized = new Temp('My App!/v2', null, false);
echo "5) appId is sanitized ('My App!/v2' -> one safe segment):\n";
echo '   ' . $sanitized->getPath() . "\n";
