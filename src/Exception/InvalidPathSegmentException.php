<?php
declare(strict_types=1);

namespace Ctw\Temp\Exception;

/**
 * Thrown when a path segment (appId or level2) is empty or unsafe after sanitization.
 */
class InvalidPathSegmentException extends InvalidArgumentException
{
}
