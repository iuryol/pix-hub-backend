<?php

namespace App\Jobs;

use App\Enums\WithdrawStatus;
use App\Events\WithdrawCompleted;
use App\Exceptions\Gateway\GatewayConnectionException;
use App\Exceptions\Gateway\GatewayRateLimitException;
use App\Exceptions\Gateway\GatewayTimeoutException;
use App\Exceptions\Gateway\InvalidWebhookPayloadException;
use App\Models\Withdrawal;
use App\Services\PaymentGateway\PaymentGatewayFactory;
use App\Services\WithdrawService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWithdrawWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 5;

    public function __construct(
        public Withdrawal $withdrawal
    ) {
        $this->onQueue('webhooks');
    }

    public function handle(
        PaymentGatewayFactory $gatewayFactory,
        WithdrawService $withdrawService
    ): void {
        // Skip if already in final state
        if ($this->withdrawal->isCompleted() || $this->withdrawal->isFailed()) {
            Log::info('Withdrawal already in final state, skipping webhook', [
                'withdrawal_id' => $this->withdrawal->id,
                'status' => $this->withdrawal->status,
            ]);
            return;
        }

        try {
            $gateway = $gatewayFactory->make($this->withdrawal->subacquirer);

            // Generate simulated webhook payload
            $webhookPayload = $gateway->generateWithdrawWebhookPayload(
                $this->withdrawal->external_id,
                (float) $this->withdrawal->amount
            );

            // Process the webhook
            $webhookData = $gateway->processWithdrawWebhook($webhookPayload);

            // Update withdrawal via service
            $withdrawal = $withdrawService->processWebhook($this->withdrawal, $webhookData);

            // Dispatch event if completed
            if ($withdrawal->isCompleted()) {
                event(new WithdrawCompleted($withdrawal));
            }

            Log::info('Withdrawal webhook simulation completed', [
                'withdrawal_id' => $withdrawal->id,
                'external_id' => $withdrawal->external_id,
                'status' => $withdrawal->status,
            ]);

        } catch (GatewayRateLimitException $e) {
            // Rate limit - release back to queue with longer delay
            $retryAfter = $e->retryAfter ?? 60;
            Log::warning('Withdrawal webhook rate limited, retrying', [
                'withdrawal_id' => $this->withdrawal->id,
                'retry_after' => $retryAfter,
            ]);
            $this->release($retryAfter);

        } catch (GatewayTimeoutException $e) {
            // Timeout - retry with backoff
            Log::warning('Withdrawal webhook timeout, retrying', [
                'withdrawal_id' => $this->withdrawal->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;

        } catch (GatewayConnectionException $e) {
            // Connection error - retry with backoff
            Log::warning('Withdrawal webhook connection error, retrying', [
                'withdrawal_id' => $this->withdrawal->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;

        } catch (InvalidWebhookPayloadException $e) {
            // Invalid payload - don't retry, mark as failed
            Log::error('Withdrawal webhook invalid payload', [
                'withdrawal_id' => $this->withdrawal->id,
                'error' => $e->getMessage(),
            ]);
            $this->withdrawal->update(['status' => WithdrawStatus::FAILED->value]);
            $this->fail($e);

        } catch (\Exception $e) {
            Log::error('Withdrawal webhook processing failed', [
                'withdrawal_id' => $this->withdrawal->id,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Withdrawal webhook job failed permanently', [
            'withdrawal_id' => $this->withdrawal->id,
            'error' => $exception->getMessage(),
            'exception' => get_class($exception),
        ]);

        // Mark withdrawal as failed after all retries exhausted
        $this->withdrawal->update(['status' => WithdrawStatus::FAILED->value]);
    }
}
