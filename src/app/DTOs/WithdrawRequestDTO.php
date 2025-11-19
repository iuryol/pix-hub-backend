<?php

namespace App\DTOs;

readonly class WithdrawRequestDTO
{
    public function __construct(
        public float $amount,
        public ?string $pixKey = null,
        public ?string $pixKeyType = null,
        public ?string $bankCode = null,
        public ?string $agency = null,
        public ?string $account = null,
        public ?string $accountType = null,
        public ?array $metadata = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'amount' => $this->amount,
            'pix_key' => $this->pixKey,
            'pix_key_type' => $this->pixKeyType,
            'bank_code' => $this->bankCode,
            'agency' => $this->agency,
            'account' => $this->account,
            'account_type' => $this->accountType,
            'metadata' => $this->metadata,
        ], fn($value) => $value !== null);
    }
}
