<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    protected function participant(): array
    {
        $user = User::factory()->create(['role' => 'participant', 'active' => true]);
        $lead = Lead::create(['name' => $user->name, 'email' => $user->email, 'consented_at' => now(), 'payment_status' => 'verified', 'user_id' => $user->id]);

        return [$user, $lead];
    }

    public function test_disabled_participant_loses_access_and_cannot_reuse_old_session_after_reenable(): void
    {
        [$user,$lead] = $this->participant();
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $this->actingAs($user)->withSession(['access_version' => 0])->get('/dashboard')->assertOk();
        $this->actingAs($admin)->post("/admin/leads/$lead->id/access", ['active' => '0'])->assertRedirect();
        $this->assertFalse($user->fresh()->active);
        $this->actingAs($user->fresh())->get('/dashboard')->assertForbidden();
        $this->actingAs($admin)->post("/admin/leads/$lead->id/access", ['active' => '1'])->assertRedirect();
        $this->actingAs($user->fresh())->withSession(['access_version' => 0])->get('/dashboard')->assertForbidden();
    }
}
