<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_manages_private_links_with_safe_schemes(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $this->actingAs($admin);
        $this->get('/admin/settings')->assertOk();
        $this->post('/admin/settings', ['whatsapp_url' => 'javascript:alert(1)', 'zoom_url' => 'https://zoom.example.test/room'])->assertSessionHasErrors('whatsapp_url');
        $this->post('/admin/settings', ['whatsapp_url' => 'https://chat.whatsapp.com/private-test', 'zoom_url' => 'https://zoom.example.test/room', 'material_url' => null])->assertRedirect('/admin/settings');
        $this->post('/logout');
        $this->get('/')->assertDontSee('private-test');
        $this->get('/dashboard')->assertRedirect('/login');
        $u = User::factory()->create(['active' => true]);
        Lead::create(['email' => $u->email, 'name' => $u->name, 'consented_at' => now(), 'user_id' => $u->id, 'payment_status' => 'verified']);
        $this->actingAs($u)->withSession(['access_version' => 0])->get('/dashboard')->assertOk()->assertSee('https://chat.whatsapp.com/private-test')->assertSee('https://zoom.example.test/room');
        $this->get('/admin/settings')->assertForbidden();
    }
}
