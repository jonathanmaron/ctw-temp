<?php
declare(strict_types=1);

namespace Ctw\Temp\Exception;

/**
 * Thrown when the POSIX extension is required but not loaded.
 */
class PosixUnavailableException extends RuntimeException
{
}
