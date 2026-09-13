<?php

namespace Tests\Feature\Authorization;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(3)->sales()->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/users');

        $response->assertOk();
    }

    public function test_sales_user_cannot_list_users(): void
    {
        $sales = User::factory()->sales()->create();

        $response = $this->actingAs($sales)->getJson('/api/v1/admin/users');

        $response->assertForbidden();
    }

    public function test_guest_cannot_list_users(): void
    {
        $this->getJson('/api/v1/admin/users')->assertUnauthorized();
    }

    public function test_admin_can_create_a_user(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/users', [
            'name' => 'New Sales Rep',
            'email' => 'newrep@pyramidth.com',
            'password' => 'password123',
            'role' => UserRole::Sales->value,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'newrep@pyramidth.com', 'role' => 'sales']);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/v1/admin/users/{$admin->id}");

        $response->assertUnprocessable();
    }

    public function test_admin_deleting_a_user_deactivates_rather_than_hard_deletes(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->sales()->create();

        $this->actingAs($admin)->deleteJson("/api/v1/admin/users/{$target->id}")->assertOk();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false]);
    }
}
