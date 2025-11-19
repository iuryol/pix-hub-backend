<?php

namespace App\DTOs;

use Carbon\Carbon;

readonly class PixCreationResult
{
    public function __construct(
        public string $externalId,
        public string $qrCode,
        public ?string $qrCodeBase64 = null,
        public ?Carbon $expiresAt = null,
        public array $rawResponse = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            externalId: $data['external_id'],
            qrCode: $data['qr_code'],
            qrCodeBase64: $data['qr_code_base64'] ?? null,
            expiresAt: isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
            rawResponse: $data,
        );
    }

    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'qr_code' => $this->qrCode,
            'qr_code_base64' => $this->qrCodeBase64,
            'expires_at' => $this->expiresAt?->toIso8601String(),
        ];
    }
}
