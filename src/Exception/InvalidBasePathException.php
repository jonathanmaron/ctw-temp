<?php
declare(strict_types=1);

namespace Ctw\Temp\Exception;

/**
 * Thrown when the configured base path is invalid (e.g. empty).
 */
class InvalidBasePathException extends InvalidArgumentException
{
}
