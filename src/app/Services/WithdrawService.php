<?php

namespace App\Services;

use App\DTOs\WebhookData;
use App\DTOs\WithdrawRequestDTO;
use App\Enums\WithdrawStatus;
use App\Exceptions\GatewayException;
use App\Jobs\ProcessWithdrawWebhook;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\PaymentGateway\PaymentGatewayFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WithdrawService
{
    public function __construct(
        private PaymentGatewayFactory $gatewayFactory
    ) {}

    /**
     * Create a new withdrawal
     */
    public function createWithdraw(
        User $user,
        float $amount,
        ?string $pixKey = null,
        ?string $pixKeyType = null,
        ?array $bankData = null
    ): Withdrawal {
        if (!$user->subacquirer) {
            throw new \InvalidArgumentException('User does not have a subacquirer configured.');
        }

        return DB::transaction(function () use ($user, $amount, $pixKey, $pixKeyType, $bankData) {
            // Create withdrawal record
            $withdrawal = Withdrawal::create([
                'user_id' => $user->id,
                'subacquirer_id' => $user->subacquirer_id,
                'amount' => $amount,
                'status' => WithdrawStatus::PENDING->value,
                'pix_key' => $pixKey,
                'pix_key_type' => $pixKeyType,
                'bank_code' => $bankData['bank_code'] ?? null,
                'bank_name' => $bankData['bank_name'] ?? null,
                'agency' => $bankData['agency'] ?? null,
                'account' => $bankData['account'] ?? null,
                'account_type' => $bankData['account_type'] ?? null,
            ]);

            try {
                $gateway = $this->gatewayFactory->make($user->subacquirer);

                $request = new WithdrawRequestDTO(
                    amount: $amount,
                    pixKey: $pixKey,
                    pixKeyType: $pixKeyType,
                    bankCode: $bankData['bank_code'] ?? null,
                    agency: $bankData['agency'] ?? null,
                    account: $bankData['account'] ?? null,
                    accountType: $bankData['account_type'] ?? null,
                );

                // Save request payload
                $withdrawal->update(['request_payload' => $request->toArray()]);

                // Call gateway API
                $response = $gateway->createWithdraw($request);

                // Update with response
                $withdrawal->update([
                    'external_id' => $response->externalId,
                    'status' => $response->status->value,
                    'response_payload' => $response->rawResponse,
                ]);

                // Dispatch webhook simulation job
                // Random delay between 2-5 seconds to simulate async processing
                ProcessWithdrawWebhook::dispatch($withdrawal)
                    ->delay(now()->addSeconds(rand(2, 5)));

                Log::info('Withdrawal created successfully', [
                    'withdrawal_id' => $withdrawal->id,
                    'external_id' => $withdrawal->external_id,
                    'user_id' => $user->id,
                    'amount' => $amount,
                ]);

                return $withdrawal->fresh();

            } catch (GatewayException $e) {
                $withdrawal->update([
                    'status' => WithdrawStatus::FAILED->value,
                    'response_payload' => $e->response,
                ]);

                Log::error('Withdrawal creation failed', [
                    'withdrawal_id' => $withdrawal->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * Process webhook and update withdrawal status
     */
    public function processWebhook(Withdrawal $withdrawal, WebhookData $webhookData): Withdrawal
    {
        $withdrawal->update([
            'status' => $webhookData->status,
            'completed_at' => $webhookData->completedAt ?? $withdrawal->completed_at,
            'webhook_payload' => $webhookData->rawPayload,
        ]);

        Log::info('Withdrawal webhook processed', [
            'withdrawal_id' => $withdrawal->id,
            'external_id' => $withdrawal->external_id,
            'new_status' => $webhookData->status,
        ]);

        return $withdrawal->fresh();
    }

    /**
     * Find withdrawal by ID
     */
    public function findById(int $id): ?Withdrawal
    {
        return Withdrawal::find($id);
    }

    /**
     * Find withdrawal by external ID and subacquirer
     */
    public function findByExternalId(string $externalId, int $subacquirerId): ?Withdrawal
    {
        return Withdrawal::where('external_id', $externalId)
            ->where('subacquirer_id', $subacquirerId)
            ->first();
    }

    /**
     * Get user's withdrawals
     */
    public function getUserWithdrawals(User $user, int $limit = 10)
    {
        return $user->withdrawals()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
