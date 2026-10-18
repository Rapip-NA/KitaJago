<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OperationalSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_stylesheet_exists_without_node_build(): void
    {
        $this->get('/')->assertOk()->assertSee('portal.css');
        $this->assertFileExists(public_path('portal.css'));
    }

    public function test_pages_prevent_caching_sensitive_content_and_referrer_leaks(): void
    {
        $this->get('/login')->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_default_seeding_never_creates_shared_accounts(): void
    {
        $this->seed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_otp_send_limits_are_configurable(): void
    {
        config(['portal.smtp_ready' => true, 'mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'portal.otp_send_email_limit' => 1]);
        Mail::fake();
        $u = User::factory()->create(['active' => true]);
        Lead::create(['name' => $u->name, 'email' => $u->email, 'user_id' => $u->id, 'payment_status' => 'verified', 'consented_at' => now()]);
        $this->post('/otp/request', ['email' => $u->email]);
        $this->travel(61)->seconds();
        $this->post('/otp/request', ['email' => $u->email]);
        Mail::assertSentCount(1);
    }
}
