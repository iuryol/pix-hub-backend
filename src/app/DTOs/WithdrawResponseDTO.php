<?php

namespace App\DTOs;

use App\Enums\WithdrawStatus;

readonly class WithdrawResponseDTO
{
    public function __construct(
        public string $externalId,
        public WithdrawStatus $status,
        public ?string $transactionId = null,
        public ?array $rawResponse = null,
    ) {}

    public static function fromSubadqA(array $response): self
    {
        return new self(
            externalId: $response['withdraw_id'] ?? '',
            status: WithdrawStatus::fromSubadqA($response['status'] ?? 'PENDING'),
            transactionId: $response['transaction_id'] ?? null,
            rawResponse: $response,
        );
    }

    public static function fromSubadqB(array $response): self
    {
        $data = $response['data'] ?? $response;

        return new self(
            externalId: $data['id'] ?? '',
            status: WithdrawStatus::fromSubadqB($data['status'] ?? 'PENDING'),
            transactionId: $data['transaction_id'] ?? null,
            rawResponse: $response,
        );
    }
}
