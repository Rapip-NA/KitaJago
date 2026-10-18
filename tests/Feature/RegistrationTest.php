<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_registration_does_not_replace_data_or_create_account(): void
    {
        $data = ['name' => '<script>alert(1)</script>', 'email' => 'test@example.test', 'consent' => 1];
        $this->post('/daftar', $data);
        $this->post('/daftar', [...$data, 'name' => 'Changed', 'email' => 'TEST@EXAMPLE.TEST']);
        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseHas('leads', ['name' => $data['name']]);
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $this->actingAs($admin)->get('/admin/leads')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>', false);
    }

    public function test_consent_and_valid_fields_are_required_and_whatsapp_is_configured(): void
    {
        $this->post('/daftar', ['name' => 'N', 'email' => 'wrong'])->assertSessionHasErrors(['email', 'consent']);
        $this->assertDatabaseCount('leads', 0);
        config(['portal.admin_whatsapp' => '6281234567890']);
        $this->get('/terima-kasih')->assertSee('https://wa.me/6281234567890', false);
        config(['portal.admin_whatsapp' => null]);
        $this->get('/terima-kasih')->assertDontSee('https://wa.me/', false);
    }

    public function test_registration_records_interest_without_creating_account(): void
    {
        $this->post('/daftar', ['name' => 'Peserta Uji', 'email' => ' PESERTA@EXAMPLE.TEST ', 'whatsapp' => null, 'consent' => '1'])->assertRedirect('/terima-kasih');
        $this->assertDatabaseHas('leads', ['email' => 'peserta@example.test', 'payment_status' => 'pending']);
        $this->assertDatabaseCount('users', 0);
        $this->get('/terima-kasih')->assertOk()->assertSee('belum mengaktifkan akun');
    }
}
