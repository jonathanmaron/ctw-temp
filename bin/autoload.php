<?php
declare(strict_types=1);

/*
 * Shared Composer-autoloader locator for the demo scripts in this directory.
 *
 * Each demo does `require __DIR__ . '/autoload.php';` before using any
 * `Ctw\Temp\*` class. We walk up the directory tree from here until we find a
 * `vendor/autoload.php`, so the demos run in either situation:
 *
 *   - standalone: after `composer install` inside this package, its own
 *     `vendor/autoload.php` is found first (nothing else is loaded);
 *   - inside the host application: the application's `vendor/autoload.php`
 *     (into which this package is symlinked) is found instead.
 */

$directory = __DIR__;

// dirname() of a filesystem root returns the root again, so this loop stops
// once we reach "/" instead of looping forever.
while ($directory !== dirname($directory)) {
    $autoloader = $directory . '/vendor/autoload.php';

    if (is_file($autoloader)) {
        require $autoloader;

        return;
    }

    $directory = dirname($directory);
}

fwrite(STDERR, 'Composer autoloader not found. Run "composer install" in the package first.' . PHP_EOL);

exit(1);
