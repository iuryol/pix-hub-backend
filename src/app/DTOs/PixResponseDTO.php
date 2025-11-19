<?php

namespace App\DTOs;

use App\Enums\PixStatus;

readonly class PixResponseDTO
{
    public function __construct(
        public string $externalId,
        public PixStatus $status,
        public ?string $qrCode = null,
        public ?string $qrCodeBase64 = null,
        public ?string $expiresAt = null,
        public ?array $rawResponse = null,
    ) {}

    public static function fromSubadqA(array $response): self
    {
        return new self(
            externalId: $response['pix_id'] ?? '',
            status: PixStatus::fromSubadqA($response['status'] ?? 'PENDING'),
            qrCode: $response['qr_code'] ?? null,
            qrCodeBase64: $response['qr_code_base64'] ?? null,
            expiresAt: $response['expires_at'] ?? null,
            rawResponse: $response,
        );
    }

    public static function fromSubadqB(array $response): self
    {
        $data = $response['data'] ?? $response;

        return new self(
            externalId: $data['id'] ?? '',
            status: PixStatus::fromSubadqB($data['status'] ?? 'PENDING'),
            qrCode: $data['qr_code'] ?? null,
            qrCodeBase64: $data['qr_code_base64'] ?? null,
            expiresAt: $data['expires_at'] ?? null,
            rawResponse: $response,
        );
    }
}
