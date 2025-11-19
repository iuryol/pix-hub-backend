<?php

namespace App\Console\Commands;

use App\DTOs\PixRequestDTO;
use App\DTOs\WithdrawRequestDTO;
use App\Models\Subacquirer;
use App\Services\PaymentGateway\PaymentGatewayFactory;
use Illuminate\Console\Command;

class TestGateway extends Command
{
    protected $signature = 'gateway:test {gateway=subadq-a} {--type=pix}';

    protected $description = 'Test gateway integration with mocks';

    public function handle(PaymentGatewayFactory $factory): int
    {
        $gatewaySlug = $this->argument('gateway');
        $type = $this->option('type');

        $subacquirer = Subacquirer::where('slug', $gatewaySlug)->first();

        if (!$subacquirer) {
            $this->error("Subacquirer '{$gatewaySlug}' not found.");
            return self::FAILURE;
        }

        $this->info("Testing {$subacquirer->name}...");

        try {
            $gateway = $factory->make($subacquirer);

            if ($type === 'pix') {
                $this->testPix($gateway);
            } else {
                $this->testWithdraw($gateway);
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function testPix($gateway): void
    {
        $this->info('Creating PIX...');

        $request = new PixRequestDTO(
            amount: 100.50,
            description: 'Test PIX Payment',
            expirationMinutes: 30,
        );

        $response = $gateway->createPix($request);

        $this->info("PIX Created Successfully!");
        $this->table(
            ['Field', 'Value'],
            [
                ['External ID', $response->externalId],
                ['Status', $response->status->value],
                ['QR Code', $response->qrCode ? 'Yes' : 'No'],
            ]
        );

        // Test webhook simulation
        $this->info("\nSimulating webhook...");
        $webhookPayload = $gateway->generatePixWebhookPayload($response->externalId, 100.50);
        $processedWebhook = $gateway->processPixWebhook($webhookPayload);

        $this->info("Webhook processed:");
        $this->table(
            ['Field', 'Value'],
            [
                ['External ID', $processedWebhook['external_id']],
                ['Status', $processedWebhook['status']->value],
                ['Amount', $processedWebhook['amount']],
                ['Payer', $processedWebhook['payer_name']],
            ]
        );
    }

    private function testWithdraw($gateway): void
    {
        $this->info('Creating Withdraw...');

        $request = new WithdrawRequestDTO(
            amount: 500.00,
            pixKey: '12345678900',
            pixKeyType: 'cpf',
        );

        $response = $gateway->createWithdraw($request);

        $this->info("Withdraw Created Successfully!");
        $this->table(
            ['Field', 'Value'],
            [
                ['External ID', $response->externalId],
                ['Status', $response->status->value],
                ['Transaction ID', $response->transactionId ?? 'N/A'],
            ]
        );

        // Test webhook simulation
        $this->info("\nSimulating webhook...");
        $webhookPayload = $gateway->generateWithdrawWebhookPayload($response->externalId, 500.00);
        $processedWebhook = $gateway->processWithdrawWebhook($webhookPayload);

        $this->info("Webhook processed:");
        $this->table(
            ['Field', 'Value'],
            [
                ['External ID', $processedWebhook['external_id']],
                ['Status', $processedWebhook['status']->value],
                ['Amount', $processedWebhook['amount']],
            ]
        );
    }
}
