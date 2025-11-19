<?php

namespace Tests\Unit\Services;

use App\DTOs\WithdrawResponseDTO;
use App\Enums\WithdrawStatus;
use App\Models\Subacquirer;
use App\Models\User;
use App\Models\Withdrawal;
use App\Contracts\PaymentGateway\PaymentGatewayInterface;
use App\Services\PaymentGateway\PaymentGatewayFactory;
use App\Services\WithdrawService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class WithdrawServiceTest extends TestCase
{
    use RefreshDatabase;

    private WithdrawService $withdrawService;
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
        $this->withdrawService = new WithdrawService($this->factory);
    }

    public function test_create_withdraw_successfully(): void
    {
        Queue::fake();

        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('createWithdraw')
            ->once()
            ->andReturn(new WithdrawResponseDTO(
                externalId: 'WD123',
                status: WithdrawStatus::PENDING,
            ));

        $this->factory->shouldReceive('make')
            ->with(Mockery::type(Subacquirer::class))
            ->once()
            ->andReturn($gateway);

        $withdrawal = $this->withdrawService->createWithdraw(
            user: $this->user,
            amount: 500.00,
            pixKey: '12345678901',
            pixKeyType: 'cpf',
        );

        $this->assertInstanceOf(Withdrawal::class, $withdrawal);
        $this->assertEquals(500.00, $withdrawal->amount);
        $this->assertEquals('WD123', $withdrawal->external_id);
        $this->assertEquals('pending', $withdrawal->status);
        $this->assertEquals('12345678901', $withdrawal->pix_key);
        $this->assertEquals('cpf', $withdrawal->pix_key_type);
        $this->assertEquals($this->user->id, $withdrawal->user_id);
    }

    public function test_get_user_withdrawals(): void
    {
        $this->user->withdrawals()->create([
            'subacquirer_id' => $this->subacquirer->id,
            'external_id' => 'WD001',
            'amount' => 200.00,
            'pix_key' => '12345678901',
            'pix_key_type' => 'cpf',
            'status' => 'success',
        ]);

        $this->user->withdrawals()->create([
            'subacquirer_id' => $this->subacquirer->id,
            'external_id' => 'WD002',
            'amount' => 300.00,
            'pix_key' => 'email@test.com',
            'pix_key_type' => 'email',
            'status' => 'pending',
        ]);

        $withdrawals = $this->withdrawService->getUserWithdrawals($this->user);

        $this->assertCount(2, $withdrawals);
    }

    public function test_find_withdrawal_by_id(): void
    {
        $withdrawal = $this->user->withdrawals()->create([
            'subacquirer_id' => $this->subacquirer->id,
            'external_id' => 'WD001',
            'amount' => 100.00,
            'pix_key' => '12345678901',
            'pix_key_type' => 'cpf',
            'status' => 'pending',
        ]);

        $found = $this->withdrawService->findById($withdrawal->id);

        $this->assertNotNull($found);
        $this->assertEquals($withdrawal->id, $found->id);
    }

    public function test_find_withdrawal_returns_null_for_invalid_id(): void
    {
        $found = $this->withdrawService->findById(99999);

        $this->assertNull($found);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
