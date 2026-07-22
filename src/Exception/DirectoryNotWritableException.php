<?php
declare(strict_types=1);

namespace Ctw\Temp\Exception;

/**
 * Thrown when the temporary directory exists but is not writable.
 */
class DirectoryNotWritableException extends RuntimeException
{
}
