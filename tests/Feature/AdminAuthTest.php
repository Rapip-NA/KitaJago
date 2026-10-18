<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_and_participant_cannot_use_admin_password_login(): void
    {
        foreach (['teacher', 'participant'] as $role) {
            $u = User::factory()->create(['role' => $role, 'active' => true]);
            $this->post('/admin/login', ['email' => $u->email, 'password' => 'password'])->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->actingAs($u)->get('/admin/leads')->assertForbidden();
            $this->get('/dashboard')->assertForbidden();
            $this->post('/logout');
        }
    }

    public function test_admin_login_is_throttled_after_five_failures(): void
    {
        $u = User::factory()->create(['role' => 'admin', 'active' => true]);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => $u->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/admin/login', ['email' => $u->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_password_login_has_separate_role_gate(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/admin/leads');
        $this->assertAuthenticatedAs($admin);
        $this->get('/admin/leads')->assertOk();
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
