<?php

namespace App\Contracts\PaymentGateway;

use App\DTOs\PixRequestDTO;
use App\DTOs\PixResponseDTO;
use App\DTOs\WebhookData;
use App\DTOs\WithdrawRequestDTO;
use App\DTOs\WithdrawResponseDTO;
use App\Exceptions\Gateway\InvalidWebhookPayloadException;
use App\Exceptions\GatewayException;

interface PaymentGatewayInterface
{
    /**
     * Get the gateway identifier (slug)
     */
    public function getIdentifier(): string;

    /**
     * Create a PIX payment
     *
     * @throws GatewayException
     */
    public function createPix(PixRequestDTO $request): PixResponseDTO;

    /**
     * Create a withdrawal
     *
     * @throws GatewayException
     */
    public function createWithdraw(WithdrawRequestDTO $request): WithdrawResponseDTO;

    /**
     * Process PIX webhook payload and return normalized data
     *
     * @throws InvalidWebhookPayloadException
     */
    public function processPixWebhook(array $payload): WebhookData;

    /**
     * Process Withdraw webhook payload and return normalized data
     *
     * @throws InvalidWebhookPayloadException
     */
    public function processWithdrawWebhook(array $payload): WebhookData;

    /**
     * Generate a simulated PIX confirmation webhook payload
     */
    public function generatePixWebhookPayload(string $externalId, float $amount): array;

    /**
     * Generate a simulated Withdraw confirmation webhook payload
     */
    public function generateWithdrawWebhookPayload(string $externalId, float $amount): array;
}
