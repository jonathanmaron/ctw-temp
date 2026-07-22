<?php
declare(strict_types=1);

namespace Ctw\Temp\Exception;

/**
 * Thrown when a path segment (id or levelN) is empty or unsafe after sanitization.
 */
class InvalidPathSegmentException extends InvalidArgumentException
{
}
