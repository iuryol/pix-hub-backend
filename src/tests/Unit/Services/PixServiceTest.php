<?php

namespace Tests\Unit\Services;

use App\DTOs\PixResponseDTO;
use App\Enums\PixStatus;
use App\Models\PixTransaction;
use App\Models\Subacquirer;
use App\Models\User;
use App\Contracts\PaymentGateway\PaymentGatewayInterface;
use App\Services\PaymentGateway\PaymentGatewayFactory;
use App\Services\PixService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class PixServiceTest extends TestCase
{
    use RefreshDatabase;

    private PixService $pixService;
    private PaymentGatewayFactory $factory;
    private User $user;
    private Subacquirer $subacquirer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subacquirer = Subacquirer::create([
            'name' => 'SubadqA',
            'slug' => 'subadq-a',
            'base_url' => 'https://mock.test',
            'credentials' => ['api_key' => 'test'],
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'subacquirer_id' => $this->subacquirer->id,
        ]);

        $this->factory = Mockery::mock(PaymentGatewayFactory::class);
        $this->pixService = new PixService($this->factory);
    }

    public function test_create_pix_successfully(): void
    {
        Queue::fake();

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('createPix')
            ->once()
            ->andReturn(new PixResponseDTO(
                externalId: 'PIX123',
                status: PixStatus::PENDING,
                qrCode: '00020126580014br.gov.bcb.pix',
                qrCodeBase64: 'base64encoded',
                expiresAt: now()->addMinutes(30),
            ));

        $this->factory->shouldReceive('make')
            ->with(Mockery::type(Subacquirer::class))
            ->once()
            ->andReturn($gateway);

        $pix = $this->pixService->createPix(
            user: $this->user,
            amount: 100.50,
            description: 'Test PIX',
        );

        $this->assertInstanceOf(PixTransaction::class, $pix);
        $this->assertEquals(100.50, $pix->amount);
        $this->assertEquals('PIX123', $pix->external_id);
        $this->assertEquals('pending', $pix->status);
        $this->assertEquals($this->user->id, $pix->user_id);
    }

    public function test_create_pix_without_description(): void
    {
        Queue::fake();

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('createPix')
            ->once()
            ->andReturn(new PixResponseDTO(
                externalId: 'PIX456',
                status: PixStatus::PENDING,
                qrCode: '00020126580014br.gov.bcb.pix',
            ));

        $this->factory->shouldReceive('make')
            ->once()
            ->andReturn($gateway);

        $pix = $this->pixService->createPix(
            user: $this->user,
            amount: 200.00,
        );

        $this->assertNull($pix->description);
        $this->assertEquals('PIX456', $pix->external_id);
    }

    public function test_get_user_transactions(): void
    {
        $this->user->pixTransactions()->create([
            'subacquirer_id' => $this->subacquirer->id,
            'external_id' => 'PIX001',
            'amount' => 50.00,
            'status' => 'paid',
        ]);

        $this->user->pixTransactions()->create([
            'subacquirer_id' => $this->subacquirer->id,
            'external_id' => 'PIX002',
            'amount' => 75.00,
            'status' => 'pending',
        ]);

        $transactions = $this->pixService->getUserTransactions($this->user);

        $this->assertCount(2, $transactions);
    }

    public function test_find_transaction_by_id(): void
    {
        $pix = $this->user->pixTransactions()->create([
            'subacquirer_id' => $this->subacquirer->id,
            'external_id' => 'PIX001',
            'amount' => 100.00,
            'status' => 'pending',
        ]);

        $found = $this->pixService->findById($pix->id);

        $this->assertNotNull($found);
        $this->assertEquals($pix->id, $found->id);
    }

    public function test_find_transaction_returns_null_for_invalid_id(): void
    {
        $found = $this->pixService->findById(99999);

        $this->assertNull($found);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
