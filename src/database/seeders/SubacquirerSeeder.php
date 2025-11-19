<?php

namespace Database\Seeders;

use App\Models\Subacquirer;
use Illuminate\Database\Seeder;

class SubacquirerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subacquirers = [
            [
                'name' => 'SubadqA',
                'slug' => 'subadq-a',
                'base_url' => 'https://0acdeaee-1729-4d55-80eb-d54a125e5e18.mock.pstmn.io',
                'credentials' => [
                    'api_key' => 'test_key_subadq_a',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'SubadqB',
                'slug' => 'subadq-b',
                'base_url' => 'https://ef8513c8-fd99-4081-8963-573cd135e133.mock.pstmn.io',
                'credentials' => [
                    'api_key' => 'test_key_subadq_b',
                ],
                'is_active' => true,
            ],
        ];

        foreach ($subacquirers as $subacquirer) {
            Subacquirer::updateOrCreate(
                ['slug' => $subacquirer['slug']],
                $subacquirer
            );
        }
    }
}
