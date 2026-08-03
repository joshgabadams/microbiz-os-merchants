<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Teller;
use App\Models\TellerBalance;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Locks in the exact allow/deny behavior manually verified via curl this
 * session: a user with the required permission succeeds, a user without
 * it gets a 403 with the expected message.
 */
class PermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    protected function makeOpenTeller(): Teller
    {
        $branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB-001',
            'office_id' => 1,
        ]);

        $teller = Teller::create([
            'branch_id' => $branch->id,
            'teller_code' => 'TLR-TEST-'.uniqid(),
            'display_name' => 'Test Teller',
            'status' => 'OPEN',
            'active' => true,
        ]);

        TellerBalance::create([
            'teller_id' => $teller->id,
            'currency' => 'NGN',
        ]);

        return $teller;
    }

    public function test_user_without_tellers_manage_permission_is_blocked(): void
    {
        $teller = $this->makeOpenTeller();

        // A plain user with no roles/permissions at all.
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/teller/close', [
            'teller_id' => $teller->id,
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Missing required permission: tellers.manage.',
        ]);
    }

    public function test_user_with_tellers_manage_permission_succeeds(): void
    {
        $teller = $this->makeOpenTeller();

        $user = User::factory()->create();
        $tellerOfficerRole = Role::where('name', 'teller-officer')->firstOrFail();
        $user->roles()->attach($tellerOfficerRole->id);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/teller/close', [
            'teller_id' => $teller->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_unauthenticated_request_is_rejected_before_permission_check(): void
    {
        $teller = $this->makeOpenTeller();

        $response = $this->postJson('/api/v1/teller/close', [
            'teller_id' => $teller->id,
        ]);

        $response->assertStatus(401);
    }
}
