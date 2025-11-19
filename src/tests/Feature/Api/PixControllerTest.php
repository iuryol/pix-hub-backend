<?php

namespace Tests\Feature\Api;

use App\Models\Subacquirer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PixControllerTest extends TestCase
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

    public function test_can_create_pix(): void
    {
        Http::fake([
            '*/pix/create' => Http::response([
                'pix_id' => 'PIX123456',
                'status' => 'PENDING',
                'qr_code' => '00020126580014br.gov.bcb.pix',
                'qr_code_base64' => 'base64encoded',
                'expires_at' => now()->addMinutes(30)->toIso8601String(),
            ], 200),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/pix', [
                'amount' => 100.50,
                'description' => 'Test PIX',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'PIX criado com sucesso.',
            ])
            ->assertJsonPath('data.amount', 100.50);

        $this->assertDatabaseHas('pix_transactions', [
            'user_id' => $this->user->id,
            'amount' => 100.50,
            'external_id' => 'PIX123456',
        ]);
    }

    public function test_cannot_create_pix_without_auth(): void
    {
        $response = $this->postJson('/api/pix', [
            'amount' => 100.50,
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Não autenticado.',
            ]);
    }

    public function test_cannot_create_pix_with_invalid_data(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/pix', [
                'amount' => -10,
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

    public function test_cannot_create_pix_without_amount(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/pix', []);

        $response->assertStatus(422)
            ->assertJsonPath('errors.amount.0', 'O valor é obrigatório.');
    }

    public function test_can_list_user_pix_transactions(): void
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

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/pix');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_can_show_pix_transaction(): void
    {
        $pix = $this->user->pixTransactions()->create([
            'subacquirer_id' => $this->subacquirer->id,
            'external_id' => 'PIX001',
            'amount' => 100.00,
            'status' => 'paid',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/pix/{$pix->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.id', $pix->id);
    }

    public function test_cannot_access_other_user_pix(): void
    {
        $otherUser = User::factory()->create([
            'subacquirer_id' => $this->subacquirer->id,
        ]);

        $pix = $otherUser->pixTransactions()->create([
            'subacquirer_id' => $this->subacquirer->id,
            'external_id' => 'PIX001',
            'amount' => 100.00,
            'status' => 'paid',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/pix/{$pix->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Acesso negado.',
            ]);
    }

    public function test_returns_404_for_nonexistent_pix(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/pix/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'PIX não encontrado.',
            ]);
    }

    public function test_user_without_subacquirer_cannot_create_pix(): void
    {
        $userWithoutSubacquirer = User::factory()->create([
            'subacquirer_id' => null,
        ]);

        $response = $this->actingAs($userWithoutSubacquirer, 'sanctum')
            ->postJson('/api/pix', [
                'amount' => 100.50,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }
}
