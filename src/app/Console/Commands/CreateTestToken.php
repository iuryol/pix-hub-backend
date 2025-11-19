<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateTestToken extends Command
{
    protected $signature = 'token:create {email?}';

    protected $description = 'Create a test API token for a user';

    public function handle(): int
    {
        $email = $this->argument('email') ?? 'user-a@example.com';

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email '{$email}' not found.");
            return self::FAILURE;
        }

        // Delete existing tokens
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('test-token')->plainTextToken;

        $this->info("Token created for {$user->name} ({$user->email})");
        $this->newLine();
        $subacquirerName = $user->subacquirer?->name ?? 'None';
        $this->line("Subacquirer: {$subacquirerName}");
        $this->newLine();
        $this->info("Token:");
        $this->line($token);
        $this->newLine();
        $this->info("Usage example:");
        $this->line("curl -H \"Authorization: Bearer {$token}\" http://localhost:8080/api/user");

        return self::SUCCESS;
    }
}
