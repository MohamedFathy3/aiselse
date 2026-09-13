<?php

namespace Tests\Feature\Authorization;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_user_can_view_their_own_lead(): void
    {
        $sales = User::factory()->sales()->create();
        $lead = Lead::factory()->create(['assigned_to' => $sales->id]);

        $this->assertTrue($sales->can('view', $lead));
    }

    public function test_sales_user_cannot_view_another_sales_users_lead(): void
    {
        $ownerA = User::factory()->sales()->create();
        $ownerB = User::factory()->sales()->create();
        $lead = Lead::factory()->create(['assigned_to' => $ownerA->id]);

        $this->assertFalse($ownerB->can('view', $lead));
    }

    public function test_admin_can_view_any_lead(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->sales()->create();
        $lead = Lead::factory()->create(['assigned_to' => $sales->id]);

        $this->assertTrue($admin->can('view', $lead));
    }

    public function test_only_admin_can_delete_a_lead(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->sales()->create();
        $lead = Lead::factory()->create(['assigned_to' => $sales->id]);

        $this->assertTrue($admin->can('delete', $lead));
        $this->assertFalse($sales->can('delete', $lead));
    }

    public function test_a_converted_lead_cannot_be_converted_again(): void
    {
        $sales = User::factory()->sales()->create();
        $lead = Lead::factory()->create([
            'assigned_to' => $sales->id,
            'status' => LeadStatus::Converted,
        ]);

        $this->assertFalse($sales->can('convert', $lead));
    }
}
