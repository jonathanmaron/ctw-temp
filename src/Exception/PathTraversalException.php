<?php
declare(strict_types=1);

namespace Ctw\Temp\Exception;

/**
 * Thrown when a file operation targets a path resolving outside the temporary directory.
 */
class PathTraversalException extends RuntimeException
{
}
