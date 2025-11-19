<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SubacquirerSeeder::class,
        ]);

        // Create test user with SubadqA
        $subacquirerA = \App\Models\Subacquirer::where('slug', 'subadq-a')->first();

        User::factory()->create([
            'name' => 'Test User A',
            'email' => 'user-a@example.com',
            'subacquirer_id' => $subacquirerA?->id,
        ]);

        // Create test user with SubadqB
        $subacquirerB = \App\Models\Subacquirer::where('slug', 'subadq-b')->first();

        User::factory()->create([
            'name' => 'Test User B',
            'email' => 'user-b@example.com',
            'subacquirer_id' => $subacquirerB?->id,
        ]);
    }
}
