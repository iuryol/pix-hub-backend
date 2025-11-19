<?php

namespace App\Services\PaymentGateway;

use App\Contracts\PaymentGateway\PaymentGatewayInterface;
use App\DTOs\PixRequestDTO;
use App\DTOs\PixResponseDTO;
use App\DTOs\WebhookData;
use App\DTOs\WithdrawRequestDTO;
use App\DTOs\WithdrawResponseDTO;
use App\Enums\PixStatus;
use App\Enums\WithdrawStatus;
use App\Models\Subacquirer;
use Illuminate\Support\Str;

class MockGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected Subacquirer $subacquirer
    ) {}

    public function getIdentifier(): string
    {
        return 'mock';
    }

    public function createPix(PixRequestDTO $request): PixResponseDTO
    {
        $externalId = 'PIX_' . Str::random(10);

        return new PixResponseDTO(
            externalId: $externalId,
            status: PixStatus::PENDING,
            qrCode: '00020126580014br.gov.bcb.pix0136' . Str::uuid() . '5204000053039865802BR5913Mock Gateway6008Sao Paulo62070503***6304',
            qrCodeBase64: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            expiresAt: now()->addMinutes(30),
            rawResponse: [
                'pix_id' => $externalId,
                'status' => 'PENDING',
                'qr_code' => 'mock_qr_code',
                'mock' => true,
            ],
        );
    }

    public function createWithdraw(WithdrawRequestDTO $request): WithdrawResponseDTO
    {
        $externalId = 'WD_' . Str::random(10);

        return new WithdrawResponseDTO(
            externalId: $externalId,
            status: WithdrawStatus::PENDING,
            rawResponse: [
                'withdraw_id' => $externalId,
                'status' => 'PENDING',
                'mock' => true,
            ],
        );
    }

    public function processPixWebhook(array $payload): WebhookData
    {
        return new WebhookData(
            externalId: $payload['pix_id'] ?? $payload['external_id'],
            status: PixStatus::PAID->value,
            amount: $payload['amount'] ?? null,
            paidAt: now(),
            rawPayload: $payload,
        );
    }

    public function processWithdrawWebhook(array $payload): WebhookData
    {
        return new WebhookData(
            externalId: $payload['withdraw_id'] ?? $payload['external_id'],
            status: WithdrawStatus::SUCCESS->value,
            amount: $payload['amount'] ?? null,
            completedAt: now(),
            rawPayload: $payload,
        );
    }

    public function generatePixWebhookPayload(string $externalId, float $amount): array
    {
        return [
            'event' => 'pix.confirmed',
            'pix_id' => $externalId,
            'status' => 'CONFIRMED',
            'amount' => $amount,
            'paid_at' => now()->toIso8601String(),
            'mock' => true,
        ];
    }

    public function generateWithdrawWebhookPayload(string $externalId, float $amount): array
    {
        return [
            'event' => 'withdraw.completed',
            'withdraw_id' => $externalId,
            'status' => 'COMPLETED',
            'amount' => $amount,
            'completed_at' => now()->toIso8601String(),
            'mock' => true,
        ];
    }
}
