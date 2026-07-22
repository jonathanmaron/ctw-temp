<?php
declare(strict_types=1);

/*
 * Demo 04 — The Posix collaborator
 * ================================
 *
 * Temp derives the per-user/group <hash> segment from the OS user and group
 * running the script, using the small `Ctw\Temp\Posix` helper. This keeps one
 * user's temporary files separate from another's on a shared host.
 *
 * Run it with:
 *     php bin/04-posix.php
 */

use Ctw\Temp\Posix;
use Ctw\Temp\Temp;

require __DIR__ . '/autoload.php';

$posix = new Posix();

// The user and group names are sanitized to lowercase [a-z0-9] so they are
// always safe to place inside a filename.
echo 'Current user:        ' . $posix->currentUser() . "\n";
echo 'Current group:       ' . $posix->currentGroup() . "\n";
echo 'Combined user_group: ' . $posix->currentUserGroup() . "\n\n";

// Temp turns that combined "user_group" value into the 8-character <hash>
// segment using crc32b. We can reproduce the calculation here to show exactly
// where the segment in the path comes from.
$expectedHash = hash('crc32b', $posix->currentUserGroup());
echo 'crc32b(user_group) = ' . $expectedHash . "   <- this becomes the <hash> segment\n\n";

// And indeed that same hash appears in the path Temp builds (default base path,
// nothing is written to disk by getPath()):
$temp = new Temp('demo.app');
echo 'Temp path:           ' . $temp->getPath() . "\n";

// Posix is injectable, so tests (or callers) can substitute their own resolver
// via the Temp constructor's last argument, e.g. `new Temp('app', posix: ...)`.
