<?php
declare(strict_types=1);

namespace CtwTest\Temp;

use PHPUnit\Framework\TestCase;

/**
 * Base test case that resets the {@see OverrideState} toggles to their
 * pass-through defaults both before and after every test.
 *
 * Resetting on both sides means a test that flips a toggle cannot leak state
 * into an unrelated test even if its own teardown is bypassed, and a new test
 * class gets correct isolation simply by extending this base — no per-class
 * teardown bookkeeping required. Any test class that touches {@see OverrideState}
 * should extend this rather than {@see TestCase} directly.
 */
abstract class AbstractTestCase extends TestCase
{
    /**
     * Resets the override toggles to their pass-through defaults before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        OverrideState::reset();
    }

    /**
     * Resets the override toggles to their pass-through defaults after each test.
     */
    protected function tearDown(): void
    {
        OverrideState::reset();

        parent::tearDown();
    }
}
