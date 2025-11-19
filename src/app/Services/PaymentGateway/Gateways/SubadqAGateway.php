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

class SubadqAGateway extends AbstractGateway
{
    /**
     * Create a PIX payment via SubadqA
     */
    public function createPix(PixRequestDTO $request): PixResponseDTO
    {
        $payload = [
            'amount' => $request->amount,
            'description' => $request->description ?? 'PIX Payment',
            'expiration_minutes' => $request->expirationMinutes ?? 30,
        ];

        $response = $this->request('POST', '/pix/create', $payload, [
            'x-mock-response-name' => '[SUCESSO_PIX] pix_create',
        ]);

        return PixResponseDTO::fromSubadqA($response);
    }

    /**
     * Create a withdrawal via SubadqA
     */
    public function createWithdraw(WithdrawRequestDTO $request): WithdrawResponseDTO
    {
        $payload = [
            'amount' => $request->amount,
            'pix_key' => $request->pixKey,
            'pix_key_type' => $request->pixKeyType ?? 'cpf',
        ];

        $response = $this->request('POST', '/withdraw', $payload, [
            'x-mock-response-name' => '[SUCESSO_WD] withdraw',
        ]);

        return WithdrawResponseDTO::fromSubadqA($response);
    }

    /**
     * Process PIX webhook payload from SubadqA
     *
     * Example payload:
     * {
     *   "event": "pix_payment_confirmed",
     *   "transaction_id": "f1a2b3c4d5e6",
     *   "pix_id": "PIX123456789",
     *   "status": "CONFIRMED",
     *   "amount": 125.50,
     *   "payer_name": "João da Silva",
     *   "payer_cpf": "12345678900",
     *   "payment_date": "2025-11-13T14:25:00Z"
     * }
     *
     * @throws InvalidWebhookPayloadException
     */
    public function processPixWebhook(array $payload): WebhookData
    {
        if (empty($payload['pix_id'])) {
            throw new InvalidWebhookPayloadException(
                gateway: $this->getIdentifier(),
                reason: 'Missing required field: pix_id',
                payload: $payload,
            );
        }

        if (!isset($payload['status'])) {
            throw new InvalidWebhookPayloadException(
                gateway: $this->getIdentifier(),
                reason: 'Missing required field: status',
                payload: $payload,
            );
        }

        $status = PixStatus::fromSubadqA($payload['status']);

        return new WebhookData(
            externalId: $payload['pix_id'],
            status: $status->value,
            amount: $payload['amount'] ?? null,
            paidAt: isset($payload['payment_date'])
                ? Carbon::parse($payload['payment_date'])
                : null,
            rawPayload: $payload,
        );
    }

    /**
     * Process Withdraw webhook payload from SubadqA
     *
     * Example payload:
     * {
     *   "event": "withdraw_completed",
     *   "withdraw_id": "WD123456789",
     *   "transaction_id": "T987654321",
     *   "status": "SUCCESS",
     *   "amount": 500.00,
     *   "completed_at": "2025-11-13T13:12:30Z"
     * }
     *
     * @throws InvalidWebhookPayloadException
     */
    public function processWithdrawWebhook(array $payload): WebhookData
    {
        if (empty($payload['withdraw_id'])) {
            throw new InvalidWebhookPayloadException(
                gateway: $this->getIdentifier(),
                reason: 'Missing required field: withdraw_id',
                payload: $payload,
            );
        }

        if (!isset($payload['status'])) {
            throw new InvalidWebhookPayloadException(
                gateway: $this->getIdentifier(),
                reason: 'Missing required field: status',
                payload: $payload,
            );
        }

        $status = WithdrawStatus::fromSubadqA($payload['status']);

        return new WebhookData(
            externalId: $payload['withdraw_id'],
            status: $status->value,
            amount: $payload['amount'] ?? null,
            completedAt: isset($payload['completed_at'])
                ? Carbon::parse($payload['completed_at'])
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
            'event' => 'pix_payment_confirmed',
            'transaction_id' => 'TXN' . uniqid(),
            'pix_id' => $externalId,
            'status' => 'CONFIRMED',
            'amount' => $amount,
            'payer_name' => 'Cliente Teste',
            'payer_cpf' => '12345678900',
            'payment_date' => now()->toIso8601String(),
            'metadata' => [
                'source' => 'SubadqA',
                'environment' => 'sandbox',
            ],
        ];
    }

    /**
     * Generate a simulated Withdraw confirmation webhook payload
     */
    public function generateWithdrawWebhookPayload(string $externalId, float $amount): array
    {
        return [
            'event' => 'withdraw_completed',
            'withdraw_id' => $externalId,
            'transaction_id' => 'TXN' . uniqid(),
            'status' => 'SUCCESS',
            'amount' => $amount,
            'requested_at' => now()->subMinutes(2)->toIso8601String(),
            'completed_at' => now()->toIso8601String(),
            'metadata' => [
                'source' => 'SubadqA',
                'destination_bank' => 'Banco Teste',
            ],
        ];
    }
}
