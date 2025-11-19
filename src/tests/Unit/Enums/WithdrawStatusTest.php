<?php

namespace Tests\Unit\Enums;

use App\Enums\WithdrawStatus;
use PHPUnit\Framework\TestCase;

class WithdrawStatusTest extends TestCase
{
    public function test_from_subadq_a_maps_correctly(): void
    {
        $this->assertEquals(WithdrawStatus::PENDING, WithdrawStatus::fromSubadqA('PENDING'));
        $this->assertEquals(WithdrawStatus::PROCESSING, WithdrawStatus::fromSubadqA('PROCESSING'));
        $this->assertEquals(WithdrawStatus::SUCCESS, WithdrawStatus::fromSubadqA('SUCCESS'));
        $this->assertEquals(WithdrawStatus::CANCELLED, WithdrawStatus::fromSubadqA('CANCELLED'));
        $this->assertEquals(WithdrawStatus::FAILED, WithdrawStatus::fromSubadqA('FAILED'));
    }

    public function test_from_subadq_b_maps_correctly(): void
    {
        $this->assertEquals(WithdrawStatus::PENDING, WithdrawStatus::fromSubadqB('PENDING'));
        $this->assertEquals(WithdrawStatus::PROCESSING, WithdrawStatus::fromSubadqB('PROCESSING'));
        $this->assertEquals(WithdrawStatus::SUCCESS, WithdrawStatus::fromSubadqB('DONE'));
        $this->assertEquals(WithdrawStatus::CANCELLED, WithdrawStatus::fromSubadqB('CANCELLED'));
        $this->assertEquals(WithdrawStatus::FAILED, WithdrawStatus::fromSubadqB('FAILED'));
    }

    public function test_is_pending_returns_correct_value(): void
    {
        $this->assertTrue(WithdrawStatus::PENDING->isPending());
        $this->assertTrue(WithdrawStatus::PROCESSING->isPending());
        $this->assertFalse(WithdrawStatus::SUCCESS->isPending());
    }

    public function test_is_completed_returns_correct_value(): void
    {
        $this->assertTrue(WithdrawStatus::SUCCESS->isCompleted());
        $this->assertTrue(WithdrawStatus::DONE->isCompleted());
        $this->assertFalse(WithdrawStatus::PENDING->isCompleted());
        $this->assertFalse(WithdrawStatus::FAILED->isCompleted());
    }

    public function test_is_final_returns_correct_value(): void
    {
        $this->assertTrue(WithdrawStatus::SUCCESS->isFinal());
        $this->assertTrue(WithdrawStatus::DONE->isFinal());
        $this->assertTrue(WithdrawStatus::CANCELLED->isFinal());
        $this->assertTrue(WithdrawStatus::FAILED->isFinal());
        $this->assertFalse(WithdrawStatus::PENDING->isFinal());
    }
}
