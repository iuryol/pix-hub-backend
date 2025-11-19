<?php

namespace App\DTOs;

use Carbon\Carbon;

readonly class WebhookData
{
    public function __construct(
        public string $externalId,
        public string $status,
        public ?float $amount = null,
        public ?Carbon $paidAt = null,
        public ?Carbon $completedAt = null,
        public ?string $failureReason = null,
        public array $rawPayload = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            externalId: $data['external_id'],
            status: $data['status'],
            amount: $data['amount'] ?? null,
            paidAt: isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : null,
            completedAt: isset($data['completed_at']) ? Carbon::parse($data['completed_at']) : null,
            failureReason: $data['failure_reason'] ?? null,
            rawPayload: $data['raw_payload'] ?? $data,
        );
    }

    public function isPaid(): bool
    {
        return in_array($this->status, ['paid', 'confirmed', 'approved']);
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'cancelled', 'rejected', 'expired']);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'status' => $this->status,
            'amount' => $this->amount,
            'paid_at' => $this->paidAt?->toIso8601String(),
            'completed_at' => $this->completedAt?->toIso8601String(),
            'failure_reason' => $this->failureReason,
            'raw_payload' => $this->rawPayload,
        ];
    }
}
