<?php
declare(strict_types=1);

namespace CtwTest\Temp;

use Ctw\Temp\Exception\DirectoryNotCreatedException;
use Ctw\Temp\Exception\DirectoryNotWritableException;
use Ctw\Temp\Exception\ExceptionInterface;
use Ctw\Temp\Exception\FileNotCreatedException;
use Ctw\Temp\Exception\InvalidArgumentException;
use Ctw\Temp\Exception\InvalidBasePathException;
use Ctw\Temp\Exception\InvalidPathSegmentException;
use Ctw\Temp\Exception\PathTraversalException;
use Ctw\Temp\Exception\PosixUnavailableException;
use Ctw\Temp\Exception\RuntimeException;
use InvalidArgumentException as SplInvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException as SplRuntimeException;
use Throwable;

/**
 * Contract tests for the package exception hierarchy.
 *
 * Every package exception must be catchable through the shared
 * {@see ExceptionInterface} marker as well as through the matching SPL base
 * class, so callers can catch broadly or narrowly. These tests pin that
 * hierarchy down for each concrete exception.
 */
#[CoversClass(RuntimeException::class)]
#[CoversClass(InvalidArgumentException::class)]
#[CoversClass(DirectoryNotCreatedException::class)]
#[CoversClass(DirectoryNotWritableException::class)]
#[CoversClass(FileNotCreatedException::class)]
#[CoversClass(PathTraversalException::class)]
#[CoversClass(PosixUnavailableException::class)]
#[CoversClass(InvalidBasePathException::class)]
#[CoversClass(InvalidPathSegmentException::class)]
final class ExceptionTest extends TestCase
{
    /**
     * Test that every package exception carries its message and is catchable via
     * {@see ExceptionInterface} and the expected SPL base class.
     *
     * @param class-string $splParent Fully qualified SPL base the exception must extend.
     */
    #[DataProvider('provideExceptions')]
    public function testPackageExceptionImplementsSharedContract(Throwable $exception, string $splParent): void
    {
        self::assertInstanceOf(ExceptionInterface::class, $exception);
        self::assertInstanceOf($splParent, $exception);
        self::assertSame('boom', $exception->getMessage());
    }

    /**
     * Provide one instance of every concrete package exception paired with the
     * SPL base class it is expected to extend.
     *
     * @return iterable<string, array{0: Throwable, 1: class-string}>
     */
    public static function provideExceptions(): iterable
    {
        yield 'RuntimeException' => [new RuntimeException('boom'), SplRuntimeException::class];
        yield 'DirectoryNotCreatedException' => [new DirectoryNotCreatedException('boom'), SplRuntimeException::class];
        yield 'DirectoryNotWritableException' => [new DirectoryNotWritableException('boom'), SplRuntimeException::class];
        yield 'FileNotCreatedException' => [new FileNotCreatedException('boom'), SplRuntimeException::class];
        yield 'PathTraversalException' => [new PathTraversalException('boom'), SplRuntimeException::class];
        yield 'PosixUnavailableException' => [new PosixUnavailableException('boom'), SplRuntimeException::class];
        yield 'InvalidArgumentException' => [new InvalidArgumentException('boom'), SplInvalidArgumentException::class];
        yield 'InvalidBasePathException' => [new InvalidBasePathException('boom'), SplInvalidArgumentException::class];
        yield 'InvalidPathSegmentException' => [new InvalidPathSegmentException('boom'), SplInvalidArgumentException::class];
    }
}
