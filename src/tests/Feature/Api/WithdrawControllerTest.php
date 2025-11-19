<?php

namespace Tests\Feature\Api;

use App\Models\Subacquirer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WithdrawControllerTest extends TestCase
{
    use RefreshDatabase;

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
    }

    public function test_can_create_withdraw(): void
    {
        Http::fake([
            '*' => Http::response([
                'withdraw_id' => 'WD123456',
                'status' => 'PENDING',
            ], 200),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/withdraw', [
                'amount' => 500.00,
                'pix_key' => '12345678901',
                'pix_key_type' => 'cpf',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Saque criado com sucesso.',
            ]);

        $this->assertDatabaseHas('withdrawals', [
            'user_id' => $this->user->id,
            'amount' => 500.00,
            'external_id' => 'WD123456',
            'pix_key' => '12345678901',
        ]);
    }

    public function test_cannot_create_withdraw_without_auth(): void
    {
        $response = $this->postJson('/api/withdraw', [
            'amount' => 500.00,
            'pix_key' => '12345678901',
            'pix_key_type' => 'cpf',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Não autenticado.',
            ]);
    }

    public function test_cannot_create_withdraw_with_invalid_amount(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/withdraw', [
                'amount' => -100,
                'pix_key' => '12345678901',
                'pix_key_type' => 'cpf',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Dados inválidos.',
            ])
            ->assertJsonStructure([
                'errors' => ['amount'],
            ]);
    }

    public function test_cannot_create_withdraw_without_required_fields(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/withdraw', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => ['amount'],
            ]);
    }

    public function test_cannot_create_withdraw_with_invalid_pix_key_type(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/withdraw', [
                'amount' => 100.00,
                'pix_key' => '12345678901',
                'pix_key_type' => 'invalid',
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => ['pix_key_type'],
            ]);
    }

    public function test_can_list_user_withdrawals(): void
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

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/withdraw');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_can_show_withdrawal(): void
    {
        $withdrawal = $this->user->withdrawals()->create([
            'subacquirer_id' => $this->subacquirer->id,
            'external_id' => 'WD001',
            'amount' => 250.00,
            'pix_key' => '12345678901',
            'pix_key_type' => 'cpf',
            'status' => 'success',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/withdraw/{$withdrawal->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.id', $withdrawal->id);
    }

    public function test_cannot_access_other_user_withdrawal(): void
    {
        $otherUser = User::factory()->create([
            'subacquirer_id' => $this->subacquirer->id,
        ]);

        $withdrawal = $otherUser->withdrawals()->create([
            'subacquirer_id' => $this->subacquirer->id,
            'external_id' => 'WD001',
            'amount' => 100.00,
            'pix_key' => '12345678901',
            'pix_key_type' => 'cpf',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/withdraw/{$withdrawal->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Acesso negado.',
            ]);
    }

    public function test_returns_404_for_nonexistent_withdrawal(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/withdraw/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_user_without_subacquirer_cannot_create_withdraw(): void
    {
        $userWithoutSubacquirer = User::factory()->create([
            'subacquirer_id' => null,
        ]);

        $response = $this->actingAs($userWithoutSubacquirer, 'sanctum')
            ->postJson('/api/withdraw', [
                'amount' => 100.00,
                'pix_key' => '12345678901',
                'pix_key_type' => 'cpf',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }
}
