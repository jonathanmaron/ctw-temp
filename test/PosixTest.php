<?php
declare(strict_types=1);

namespace CtwTest\Temp;

use Ctw\Temp\Exception\PosixUnavailableException;
use Ctw\Temp\Posix;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/_support/function_overrides.php';

/**
 * Unit tests for {@see Posix}.
 *
 * These tests assert the shape and sanitization guarantees of the resolved
 * values rather than a specific user/group, so they remain valid regardless of
 * the account running the suite.
 */
#[CoversClass(Posix::class)]
final class PosixTest extends TestCase
{
    protected function tearDown(): void
    {
        OverrideState::reset();
    }

    /**
     * Test that the current user is a non-empty string of lowercase alphanumerics.
     */
    public function testCurrentUserIsSanitizedLowercaseAlphanumeric(): void
    {
        $posix = new Posix();

        self::assertMatchesRegularExpression('#^[a-z0-9]+$#', $posix->currentUser());
    }

    /**
     * Test that the current group is a non-empty string of lowercase alphanumerics.
     */
    public function testCurrentGroupIsSanitizedLowercaseAlphanumeric(): void
    {
        $posix = new Posix();

        self::assertMatchesRegularExpression('#^[a-z0-9]+$#', $posix->currentGroup());
    }

    /**
     * Test that the combined value is `<user>_<group>` built from the individual parts.
     */
    public function testCurrentUserGroupCombinesUserAndGroupWithUnderscore(): void
    {
        $posix = new Posix();

        self::assertSame(
            sprintf('%s_%s', $posix->currentUser(), $posix->currentGroup()),
            $posix->currentUserGroup(),
        );
    }

    /**
     * Test that resolving the current user throws when the POSIX extension is unavailable.
     *
     * @throws PosixUnavailableException Always, once the extension is reported as absent.
     */
    public function testCurrentUserThrowsWhenPosixExtensionIsUnavailable(): void
    {
        OverrideState::$posixExtensionAvailable = false;

        $this->expectException(PosixUnavailableException::class);

        (void) (new Posix())->currentUser();
    }
}
