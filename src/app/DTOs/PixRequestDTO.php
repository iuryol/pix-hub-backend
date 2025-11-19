<?php

namespace App\DTOs;

readonly class PixRequestDTO
{
    public function __construct(
        public float $amount,
        public ?string $description = null,
        public ?int $expirationMinutes = 30,
        public ?array $metadata = null,
    ) {}

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'description' => $this->description,
            'expiration_minutes' => $this->expirationMinutes,
            'metadata' => $this->metadata,
        ];
    }
}
