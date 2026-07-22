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
 *     <basePath>[/<hash>]/<id>[/<levelN>]
 *
 * The optional `<levelN>` part accepts either a single directory name or a list
 * of names that nest one directory per element (see examples 2 and 3).
 *
 * Run it with:
 *     php bin/01-path-model.php
 */

use Ctw\Temp\Temp;

require __DIR__ . '/autoload.php';

// 1) The simplest possible instance: only the required $id is given.
//    - $basePath defaults to /var/tmp/php
//    - the per-user/group <hash> segment is included by default
//    - there is no optional <levelN> segment
$default = new Temp('www.example.com');
echo "1) Defaults (id only):\n";
echo '   ' . $default->getPath() . "\n\n";

// 2) Add an optional n-level directory (2nd argument) as a single string. Handy
//    for grouping, e.g. a page cache that you can clear independently.
$withLevelN = new Temp('www.example.com', 'page-cache');
echo "2) With a single levelN directory ('page-cache'):\n";
echo '   ' . $withLevelN->getPath() . "\n\n";

// 3) Pass a LIST for $levelN to nest one directory per element. Each element is
//    sanitized independently, so ['Page Cache!', 'v2', 'html'] nests as the
//    sub-path PageCache/v2/html. An empty or unsafe element (e.g. '' or '..') is
//    rejected with an InvalidPathSegmentException.
$nested = new Temp('www.example.com', ['Page Cache!', 'v2', 'html']);
echo "3) With a nested, per-element-sanitized levelN list (['Page Cache!', 'v2', 'html']):\n";
echo '   ' . $nested->getPath() . "\n\n";

// 4) Turn OFF the per-user/group <hash> segment (3rd argument = false). Use this
//    when every process should share one directory instead of one per user.
$noHash = new Temp('www.example.com', null, false);
echo "4) Without the <hash> segment (includeUserGroup = false):\n";
echo '   ' . $noHash->getPath() . "\n\n";

// 5) Override the base path (4th argument). The default /var/tmp/php lives on
//    real disk; you can point the tree anywhere you like.
$customBase = new Temp('www.example.com', 'cache', false, '/srv/tmp');
echo "5) Custom base path ('/srv/tmp'):\n";
echo '   ' . $customBase->getPath() . "\n\n";

// 6) Unsafe characters in id/levelN are stripped so each value is always a
//    single, safe path segment. 'My App!/v2' collapses to 'MyAppv2'.
$sanitized = new Temp('My App!/v2', null, false);
echo "6) id is sanitized ('My App!/v2' -> one safe segment):\n";
echo '   ' . $sanitized->getPath() . "\n";
