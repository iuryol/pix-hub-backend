<?php

namespace App\DTOs;

readonly class WithdrawCreationResult
{
    public function __construct(
        public string $externalId,
        public string $status,
        public array $rawResponse = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            externalId: $data['external_id'],
            status: $data['status'] ?? 'pending',
            rawResponse: $data,
        );
    }

    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'status' => $this->status,
        ];
    }
}
