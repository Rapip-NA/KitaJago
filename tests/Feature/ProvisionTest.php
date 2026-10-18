<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProvisionTest extends TestCase
{
    use RefreshDatabase;

    protected function lead(): Lead
    {
        return Lead::create(['name' => 'Peserta Uji', 'email' => 'peserta@example.test', 'consented_at' => now()]);
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'active' => true]);
    }

    public function test_pending_and_rejected_payments_cannot_provision(): void
    {
        $lead = $this->lead();
        $this->actingAs($this->admin());
        $this->post("/admin/leads/$lead->id/provision")->assertUnprocessable();
        $this->post("/admin/leads/$lead->id/payment", ['status' => 'rejected'])->assertRedirect();
        $this->post("/admin/leads/$lead->id/provision")->assertUnprocessable();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_repeated_provision_is_idempotent(): void
    {
        $lead = $this->lead();
        $this->actingAs($this->admin());
        $this->post("/admin/leads/$lead->id/payment", ['status' => 'verified']);
        $this->post("/admin/leads/$lead->id/provision")->assertRedirect();
        $id = $lead->fresh()->user_id;
        $this->post("/admin/leads/$lead->id/provision")->assertRedirect();
        $this->assertSame($id, $lead->fresh()->user_id);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('audit_events', 2);
    }

    public function test_existing_admin_or_teacher_email_cannot_be_overwritten(): void
    {
        $lead = $this->lead();
        $this->actingAs($this->admin());
        $this->post("/admin/leads/$lead->id/payment", ['status' => 'verified']);
        $existing = User::factory()->create(['email' => $lead->email, 'role' => 'teacher']);
        $this->post("/admin/leads/$lead->id/provision")->assertConflict();
        $this->assertSame('teacher', $existing->fresh()->role);
        $existing->role = 'admin';
        $existing->save();
        $this->post("/admin/leads/$lead->id/provision")->assertConflict();
        $this->assertNull($lead->fresh()->user_id);
    }

    public function test_reverting_payment_revokes_access_and_requires_explicit_reactivation(): void
    {
        $lead = $this->lead();
        $this->actingAs($this->admin());
        $this->post("/admin/leads/$lead->id/payment", ['status' => 'verified']);
        $this->post("/admin/leads/$lead->id/provision");
        $this->post("/admin/leads/$lead->id/payment", ['status' => 'pending']);
        $u = User::findOrFail($lead->fresh()->user_id);
        $this->assertFalse($u->active);
        $this->assertSame(1, $u->access_version);
        $this->post("/admin/leads/$lead->id/access", ['active' => 1])->assertUnprocessable();
        $this->post("/admin/leads/$lead->id/payment", ['status' => 'verified']);
        $this->assertFalse($u->fresh()->active);
    }

    public function test_payment_and_provision_are_distinct_audited_actions(): void
    {
        $lead = $this->lead();
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->post("/admin/leads/$lead->id/payment", ['status' => 'verified'])->assertRedirect();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'payment_status' => 'verified', 'verified_by' => $admin->id]);
        $this->assertNotNull($lead->fresh()->verified_at);
        $this->post("/admin/leads/$lead->id/provision")->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => $lead->email, 'role' => 'participant', 'active' => true]);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $admin->id, 'lead_id' => $lead->id, 'action' => 'provision']);
        $this->get("/admin/leads/$lead->id")->assertOk()->assertSee($lead->email);
    }
}
