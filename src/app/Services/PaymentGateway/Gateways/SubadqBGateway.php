<?php

namespace App\Services\PaymentGateway\Gateways;

use App\DTOs\PixRequestDTO;
use App\DTOs\PixResponseDTO;
use App\DTOs\WebhookData;
use App\DTOs\WithdrawRequestDTO;
use App\DTOs\WithdrawResponseDTO;
use App\Enums\PixStatus;
use App\Enums\WithdrawStatus;
use App\Exceptions\Gateway\InvalidWebhookPayloadException;
use Carbon\Carbon;

class SubadqBGateway extends AbstractGateway
{
    /**
     * Create a PIX payment via SubadqB
     */
    public function createPix(PixRequestDTO $request): PixResponseDTO
    {
        $payload = [
            'value' => $request->amount,
            'description' => $request->description ?? 'PIX Payment',
            'expiration' => $request->expirationMinutes ?? 30,
        ];

        $response = $this->request('POST', '/pix/create', $payload, [
            'x-mock-response-name' => '[SUCESSO_PIX] pix_create',
        ]);

        return PixResponseDTO::fromSubadqB($response);
    }

    /**
     * Create a withdrawal via SubadqB
     */
    public function createWithdraw(WithdrawRequestDTO $request): WithdrawResponseDTO
    {
        $payload = [
            'amount' => $request->amount,
            'destination' => [
                'pix_key' => $request->pixKey,
                'type' => $request->pixKeyType ?? 'cpf',
            ],
        ];

        $response = $this->request('POST', '/withdraw', $payload, [
            'x-mock-response-name' => '[SUCESSO_WD] withdraw',
        ]);

        return WithdrawResponseDTO::fromSubadqB($response);
    }

    /**
     * Process PIX webhook payload from SubadqB
     *
     * Example payload:
     * {
     *   "type": "pix.status_update",
     *   "data": {
     *     "id": "PX987654321",
     *     "status": "PAID",
     *     "value": 250.00,
     *     "payer": {
     *       "name": "Maria Oliveira",
     *       "document": "98765432100"
     *     },
     *     "confirmed_at": "2025-11-13T14:40:00Z"
     *   },
     *   "signature": "d1c4b6f98eaa"
     * }
     *
     * @throws InvalidWebhookPayloadException
     */
    public function processPixWebhook(array $payload): WebhookData
    {
        $data = $payload['data'] ?? [];

        if (empty($data['id'])) {
            throw new InvalidWebhookPayloadException(
                gateway: $this->getIdentifier(),
                reason: 'Missing required field: data.id',
                payload: $payload,
            );
        }

        if (!isset($data['status'])) {
            throw new InvalidWebhookPayloadException(
                gateway: $this->getIdentifier(),
                reason: 'Missing required field: data.status',
                payload: $payload,
            );
        }

        $status = PixStatus::fromSubadqB($data['status']);

        return new WebhookData(
            externalId: $data['id'],
            status: $status->value,
            amount: $data['value'] ?? null,
            paidAt: isset($data['confirmed_at'])
                ? Carbon::parse($data['confirmed_at'])
                : null,
            rawPayload: $payload,
        );
    }

    /**
     * Process Withdraw webhook payload from SubadqB
     *
     * Example payload:
     * {
     *   "type": "withdraw.status_update",
     *   "data": {
     *     "id": "WDX54321",
     *     "status": "DONE",
     *     "amount": 850.00,
     *     "bank_account": {
     *       "bank": "Nubank",
     *       "agency": "0001",
     *       "account": "1234567-8"
     *     },
     *     "processed_at": "2025-11-13T13:45:10Z"
     *   },
     *   "signature": "aabbccddeeff112233"
     * }
     *
     * @throws InvalidWebhookPayloadException
     */
    public function processWithdrawWebhook(array $payload): WebhookData
    {
        $data = $payload['data'] ?? [];

        if (empty($data['id'])) {
            throw new InvalidWebhookPayloadException(
                gateway: $this->getIdentifier(),
                reason: 'Missing required field: data.id',
                payload: $payload,
            );
        }

        if (!isset($data['status'])) {
            throw new InvalidWebhookPayloadException(
                gateway: $this->getIdentifier(),
                reason: 'Missing required field: data.status',
                payload: $payload,
            );
        }

        $status = WithdrawStatus::fromSubadqB($data['status']);

        return new WebhookData(
            externalId: $data['id'],
            status: $status->value,
            amount: $data['amount'] ?? null,
            completedAt: isset($data['processed_at'])
                ? Carbon::parse($data['processed_at'])
                : null,
            rawPayload: $payload,
        );
    }

    /**
     * Generate a simulated PIX confirmation webhook payload
     */
    public function generatePixWebhookPayload(string $externalId, float $amount): array
    {
        return [
            'type' => 'pix.status_update',
            'data' => [
                'id' => $externalId,
                'status' => 'PAID',
                'value' => $amount,
                'payer' => [
                    'name' => 'Cliente Teste',
                    'document' => '98765432100',
                ],
                'confirmed_at' => now()->toIso8601String(),
            ],
            'signature' => bin2hex(random_bytes(6)),
        ];
    }

    /**
     * Generate a simulated Withdraw confirmation webhook payload
     */
    public function generateWithdrawWebhookPayload(string $externalId, float $amount): array
    {
        return [
            'type' => 'withdraw.status_update',
            'data' => [
                'id' => $externalId,
                'status' => 'DONE',
                'amount' => $amount,
                'bank_account' => [
                    'bank' => 'Banco Teste',
                    'agency' => '0001',
                    'account' => '12345-6',
                ],
                'processed_at' => now()->toIso8601String(),
            ],
            'signature' => bin2hex(random_bytes(9)),
        ];
    }
}
