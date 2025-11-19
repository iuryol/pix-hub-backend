<?php

namespace Tests\Unit\Enums;

use App\Enums\PixStatus;
use PHPUnit\Framework\TestCase;

class PixStatusTest extends TestCase
{
    public function test_from_subadq_a_maps_correctly(): void
    {
        $this->assertEquals(PixStatus::PENDING, PixStatus::fromSubadqA('PENDING'));
        $this->assertEquals(PixStatus::PROCESSING, PixStatus::fromSubadqA('PROCESSING'));
        $this->assertEquals(PixStatus::PAID, PixStatus::fromSubadqA('CONFIRMED'));
        $this->assertEquals(PixStatus::CANCELLED, PixStatus::fromSubadqA('CANCELLED'));
        $this->assertEquals(PixStatus::FAILED, PixStatus::fromSubadqA('FAILED'));
    }

    public function test_from_subadq_b_maps_correctly(): void
    {
        $this->assertEquals(PixStatus::PENDING, PixStatus::fromSubadqB('PENDING'));
        $this->assertEquals(PixStatus::PROCESSING, PixStatus::fromSubadqB('PROCESSING'));
        $this->assertEquals(PixStatus::PAID, PixStatus::fromSubadqB('PAID'));
        $this->assertEquals(PixStatus::CANCELLED, PixStatus::fromSubadqB('CANCELLED'));
        $this->assertEquals(PixStatus::FAILED, PixStatus::fromSubadqB('FAILED'));
    }

    public function test_is_pending_returns_correct_value(): void
    {
        $this->assertTrue(PixStatus::PENDING->isPending());
        $this->assertTrue(PixStatus::PROCESSING->isPending());
        $this->assertFalse(PixStatus::PAID->isPending());
        $this->assertFalse(PixStatus::CONFIRMED->isPending());
    }

    public function test_is_paid_returns_correct_value(): void
    {
        $this->assertTrue(PixStatus::PAID->isPaid());
        $this->assertTrue(PixStatus::CONFIRMED->isPaid());
        $this->assertFalse(PixStatus::PENDING->isPaid());
        $this->assertFalse(PixStatus::FAILED->isPaid());
    }

    public function test_is_final_returns_correct_value(): void
    {
        $this->assertTrue(PixStatus::PAID->isFinal());
        $this->assertTrue(PixStatus::CONFIRMED->isFinal());
        $this->assertTrue(PixStatus::CANCELLED->isFinal());
        $this->assertTrue(PixStatus::FAILED->isFinal());
        $this->assertFalse(PixStatus::PENDING->isFinal());
        $this->assertFalse(PixStatus::PROCESSING->isFinal());
    }

    public function test_unknown_status_defaults_to_pending(): void
    {
        $this->assertEquals(PixStatus::PENDING, PixStatus::fromSubadqA('UNKNOWN'));
        $this->assertEquals(PixStatus::PENDING, PixStatus::fromSubadqB('UNKNOWN'));
    }
}
