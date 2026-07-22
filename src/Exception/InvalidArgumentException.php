<?php
declare(strict_types=1);

namespace Ctw\Temp\Exception;

/**
 * Base class for exceptions caused by invalid arguments passed to the package.
 */
class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface
{
}
