<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_does_not_seed_demo_account_in_production(): void
    {
        // Set environment to production
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        $demoUser = User::where('email', 'user@operations-hub.local')->first();
        $this->assertNull($demoUser, 'Demo account must never be created in production environment.');
    }

    public function test_seeder_seeds_demo_account_in_local_environment(): void
    {
        // Testing environment is non-production
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        $demoUser = User::where('email', 'user@operations-hub.local')->first();
        $this->assertNotNull($demoUser, 'Demo account should be created in non-production environments.');
        $this->assertNotNull($demoUser->email_verified_at, 'Demo user should be pre-verified for seamless local DX.');
    }
}
