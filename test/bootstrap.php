<?php
declare(strict_types=1);

// Composer autoloader for both the package and the test classes.
require_once __DIR__ . '/../vendor/autoload.php';

// Install the namespaced function overrides once for the whole suite, so their
// effect never depends on an individual test remembering to require the file.
require_once __DIR__ . '/_support/function_overrides.php';
