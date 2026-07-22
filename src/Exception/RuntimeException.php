<?php
declare(strict_types=1);

namespace Ctw\Temp\Exception;

/**
 * Base class for exceptions caused by runtime failures within the package.
 */
class RuntimeException extends \RuntimeException implements ExceptionInterface
{
}
