<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_receives_a_token(): void
    {
        User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->postJson('/api/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->postJson('/api/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'krivo',
        ])->assertStatus(401);
    }

    public function test_non_admin_cannot_log_in(): void
    {
        User::factory()->create(['email' => 'user@example.com']);

        $this->postJson('/api/admin/login', [
            'email' => 'user@example.com',
            'password' => 'password',
        ])->assertStatus(401);
    }

    public function test_unknown_email_is_rejected(): void
    {
        $this->postJson('/api/admin/login', [
            'email' => 'nitko@example.com',
            'password' => 'password',
        ])->assertStatus(401);
    }

    public function test_credentials_are_validated(): void
    {
        $this->postJson('/api/admin/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
