<?php

namespace App\Jobs;

use App\Enums\PixStatus;
use App\Events\PixConfirmed;
use App\Exceptions\Gateway\GatewayConnectionException;
use App\Exceptions\Gateway\GatewayRateLimitException;
use App\Exceptions\Gateway\GatewayTimeoutException;
use App\Exceptions\Gateway\InvalidWebhookPayloadException;
use App\Models\PixTransaction;
use App\Services\PaymentGateway\PaymentGatewayFactory;
use App\Services\PixService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPixWebhook implements ShouldQueue
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
        public PixTransaction $pix
    ) {
        $this->onQueue('webhooks');
    }

    public function handle(
        PaymentGatewayFactory $gatewayFactory,
        PixService $pixService
    ): void {
        // Skip if already in final state
        if ($this->pix->isPaid() || $this->pix->isFailed()) {
            Log::info('PIX already in final state, skipping webhook', [
                'pix_id' => $this->pix->id,
                'status' => $this->pix->status,
            ]);
            return;
        }

        try {
            $gateway = $gatewayFactory->make($this->pix->subacquirer);

            // Generate simulated webhook payload
            $webhookPayload = $gateway->generatePixWebhookPayload(
                $this->pix->external_id,
                (float) $this->pix->amount
            );

            // Process the webhook
            $webhookData = $gateway->processPixWebhook($webhookPayload);

            // Update PIX via service
            $pix = $pixService->processWebhook($this->pix, $webhookData);

            // Dispatch event if payment confirmed
            if ($pix->isPaid()) {
                event(new PixConfirmed($pix));
            }

            Log::info('PIX webhook simulation completed', [
                'pix_id' => $pix->id,
                'external_id' => $pix->external_id,
                'status' => $pix->status,
            ]);

        } catch (GatewayRateLimitException $e) {
            // Rate limit - release back to queue with longer delay
            $retryAfter = $e->retryAfter ?? 60;
            Log::warning('PIX webhook rate limited, retrying', [
                'pix_id' => $this->pix->id,
                'retry_after' => $retryAfter,
            ]);
            $this->release($retryAfter);

        } catch (GatewayTimeoutException $e) {
            // Timeout - retry with backoff
            Log::warning('PIX webhook timeout, retrying', [
                'pix_id' => $this->pix->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;

        } catch (GatewayConnectionException $e) {
            // Connection error - retry with backoff
            Log::warning('PIX webhook connection error, retrying', [
                'pix_id' => $this->pix->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;

        } catch (InvalidWebhookPayloadException $e) {
            // Invalid payload - don't retry, mark as failed
            Log::error('PIX webhook invalid payload', [
                'pix_id' => $this->pix->id,
                'error' => $e->getMessage(),
            ]);
            $this->pix->update(['status' => PixStatus::FAILED->value]);
            $this->fail($e);

        } catch (\Exception $e) {
            Log::error('PIX webhook processing failed', [
                'pix_id' => $this->pix->id,
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
        Log::error('PIX webhook job failed permanently', [
            'pix_id' => $this->pix->id,
            'error' => $exception->getMessage(),
            'exception' => get_class($exception),
        ]);

        // Mark PIX as failed after all retries exhausted
        $this->pix->update(['status' => PixStatus::FAILED->value]);
    }
}
