<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'sales@pyramidth.com',
            'password' => bcrypt('correct-password'),
            'role' => UserRole::Sales,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'sales@pyramidth.com',
            'password' => 'correct-password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('email', $user->email);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_incorrect_password(): void
    {
        User::factory()->create([
            'email' => 'sales@pyramidth.com',
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'sales@pyramidth.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_deactivated_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inactive@pyramidth.com',
            'password' => bcrypt('correct-password'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@pyramidth.com',
            'password' => 'correct-password',
        ]);

        $response->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_authenticated_user_can_fetch_their_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/auth/me');

        $response->assertOk();
        $response->assertJsonPath('id', $user->id);
        // Google tokens must never leak, even if present on the model.
        $response->assertJsonMissing(['google_access_token', 'google_refresh_token']);
    }

    public function test_guest_cannot_fetch_profile(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/auth/logout');

        $response->assertOk();
        $this->assertGuest();
    }
}
