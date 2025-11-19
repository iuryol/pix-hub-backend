<?php

namespace App\Services;

use App\DTOs\PixRequestDTO;
use App\DTOs\WebhookData;
use App\Enums\PixStatus;
use App\Exceptions\GatewayException;
use App\Jobs\ProcessPixWebhook;
use App\Models\PixTransaction;
use App\Models\User;
use App\Services\PaymentGateway\PaymentGatewayFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PixService
{
    public function __construct(
        private PaymentGatewayFactory $gatewayFactory
    ) {}

    /**
     * Create a new PIX transaction
     */
    public function createPix(User $user, float $amount, ?string $description = null): PixTransaction
    {
        if (!$user->subacquirer) {
            throw new \InvalidArgumentException('User does not have a subacquirer configured.');
        }

        return DB::transaction(function () use ($user, $amount, $description) {
            // Create transaction record
            $pix = PixTransaction::create([
                'user_id' => $user->id,
                'subacquirer_id' => $user->subacquirer_id,
                'amount' => $amount,
                'status' => PixStatus::PENDING->value,
            ]);

            try {
                $gateway = $this->gatewayFactory->make($user->subacquirer);

                $request = new PixRequestDTO(
                    amount: $amount,
                    description: $description,
                    expirationMinutes: 30,
                );

                // Save request payload
                $pix->update(['request_payload' => $request->toArray()]);

                // Call gateway API
                $response = $gateway->createPix($request);

                // Update with response
                $pix->update([
                    'external_id' => $response->externalId,
                    'status' => $response->status->value,
                    'qr_code' => $response->qrCode,
                    'qr_code_base64' => $response->qrCodeBase64,
                    'response_payload' => $response->rawResponse,
                    'expires_at' => $response->expiresAt ? now()->parse($response->expiresAt) : now()->addMinutes(30),
                ]);

                // Dispatch webhook simulation job
                // Random delay between 2-5 seconds to simulate async processing
                ProcessPixWebhook::dispatch($pix)
                    ->delay(now()->addSeconds(rand(2, 5)));

                Log::info('PIX created successfully', [
                    'pix_id' => $pix->id,
                    'external_id' => $pix->external_id,
                    'user_id' => $user->id,
                    'amount' => $amount,
                ]);

                return $pix->fresh();

            } catch (GatewayException $e) {
                $pix->update([
                    'status' => PixStatus::FAILED->value,
                    'response_payload' => $e->response,
                ]);

                Log::error('PIX creation failed', [
                    'pix_id' => $pix->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * Process webhook and update PIX status
     */
    public function processWebhook(PixTransaction $pix, WebhookData $webhookData): PixTransaction
    {
        $pix->update([
            'status' => $webhookData->status,
            'paid_at' => $webhookData->paidAt ?? $pix->paid_at,
            'webhook_payload' => $webhookData->rawPayload,
        ]);

        Log::info('PIX webhook processed', [
            'pix_id' => $pix->id,
            'external_id' => $pix->external_id,
            'new_status' => $webhookData->status,
        ]);

        return $pix->fresh();
    }

    /**
     * Find PIX by ID
     */
    public function findById(int $id): ?PixTransaction
    {
        return PixTransaction::find($id);
    }

    /**
     * Find PIX by external ID and subacquirer
     */
    public function findByExternalId(string $externalId, int $subacquirerId): ?PixTransaction
    {
        return PixTransaction::where('external_id', $externalId)
            ->where('subacquirer_id', $subacquirerId)
            ->first();
    }

    /**
     * Get user's PIX transactions
     */
    public function getUserTransactions(User $user, int $limit = 10)
    {
        return $user->pixTransactions()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
