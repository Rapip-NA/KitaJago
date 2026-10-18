<?php

namespace Tests\Feature;

use App\Mail\ParticipantOtp;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class OtpTest extends TestCase
{
    use RefreshDatabase;

    protected function ready(): User
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'portal.smtp_ready' => true]);
        Mail::fake();
        $u = User::factory()->create(['active' => true]);
        Lead::create(['name' => $u->name, 'email' => $u->email, 'user_id' => $u->id, 'payment_status' => 'verified', 'consented_at' => now()]);

        return $u;
    }

    public function test_unknown_pending_inactive_and_teacher_emails_receive_identical_response(): void
    {
        $u = $this->ready();
        $this->post('/otp/request', ['email' => 'unknown@example.test'])->assertRedirect('/login');
        $generic = session('status');
        $this->post('/otp/request', ['email' => $u->email])->assertSessionHas('status', $generic);
        $u->active = false;
        $u->save();
        $this->post('/otp/request', ['email' => $u->email])->assertSessionHas('status', $generic);
        $u->active = true;
        $u->role = 'teacher';
        $u->save();
        $this->post('/otp/request', ['email' => $u->email])->assertSessionHas('status', $generic);
        $u->role = 'participant';
        $u->save();
        Lead::query()->update(['payment_status' => 'pending']);
        $this->post('/otp/request', ['email' => $u->email])->assertSessionHas('status', $generic);
        Mail::assertSentCount(1);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_resend_cooldown_replaces_old_challenge_and_expiry_denies_login(): void
    {
        $u = $this->ready();
        $this->post('/otp/request', ['email' => $u->email]);
        $first = Mail::sent(ParticipantOtp::class)->first()->code;
        $this->post('/otp/request', ['email' => $u->email]);
        Mail::assertSentCount(1);
        $this->travel(61)->seconds();
        $this->post('/otp/request', ['email' => $u->email]);
        Mail::assertSentCount(2);
        $second = Mail::sent(ParticipantOtp::class)->last()->code;
        // Very rare identical random codes are valid replacements; use hash change to assert invalidation.
        $this->assertDatabaseCount('otp_challenges', 1);
        if ($first !== $second) {
            $this->post('/otp/verify', ['email' => $u->email, 'code' => $first])->assertSessionHasErrors('code');
        }
        $this->travel(11)->minutes();
        $this->post('/otp/verify', ['email' => $u->email, 'code' => $second])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_five_wrong_attempts_exhaust_challenge(): void
    {
        $u = $this->ready();
        $this->post('/otp/request', ['email' => $u->email]);
        $code = Mail::sent(ParticipantOtp::class)->first()->code;
        $wrong = $code === '000000' ? '000001' : '000000';
        for ($i = 0; $i < 5; $i++) {
            $this->post('/otp/verify', ['email' => $u->email, 'code' => $wrong])->assertSessionHasErrors('code');
        }
        $this->post('/otp/verify', ['email' => $u->email, 'code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertSame(5, DB::table('otp_challenges')->first()->attempts);
    }

    public function test_email_rate_limit_and_revocation_invalidate_codes(): void
    {
        $u = $this->ready();
        for ($i = 0; $i < 6; $i++) {
            $this->post('/otp/request', ['email' => $u->email]);
            $this->travel(61)->seconds();
        }
        Mail::assertSentCount(5);
        $code = Mail::sent(ParticipantOtp::class)->last()->code;
        $u->increment('access_version');
        $this->post('/otp/verify', ['email' => $u->email, 'code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_missing_smtp_never_falls_back_to_log_mail(): void
    {
        $this->ready();
        config(['portal.smtp_ready' => false]);
        $this->post('/otp/request', ['email' => 'unknown@example.test'])->assertSessionHasErrors('email');
        Mail::assertNothingSent();
        config(['portal.smtp_ready' => true, 'mail.default' => 'log']);
        $this->post('/otp/request', ['email' => 'unknown@example.test'])->assertSessionHasErrors('email');
        Mail::assertNothingSent();
    }

    public function test_verification_email_rate_limit_prevents_even_correct_code(): void
    {
        $u = $this->ready();
        $this->post('/otp/request', ['email' => $u->email]);
        $code = Mail::sent(ParticipantOtp::class)->first()->code;
        for ($i = 0; $i < 30; $i++) {
            RateLimiter::hit('otp-verify-email:'.hash('sha256', $u->email), 300);
        }
        $this->post('/otp/verify', ['email' => $u->email, 'code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_ip_send_limit_blocks_other_addresses(): void
    {
        $u = $this->ready();
        for ($i = 0; $i < 20; $i++) {
            RateLimiter::hit('otp-send-ip:'.hash('sha256', '127.0.0.1'), 3600);
        }
        $this->post('/otp/request', ['email' => $u->email])->assertRedirect('/login');
        Mail::assertNothingSent();
    }

    public function test_sensitive_code_is_never_flashed_to_session(): void
    {
        $this->post('/otp/verify', ['email' => 'not-an-email', 'code' => '123456'])->assertSessionHasErrors('email');
        $this->assertArrayNotHasKey('code', session()->getOldInput());
    }

    public function test_eligible_participant_receives_hashed_code_and_logs_in_once(): void
    {
        $u = $this->ready();
        $this->get('/login')->assertOk();
        $this->post('/otp/request', ['email' => strtoupper($u->email)])->assertRedirect('/login');
        $mail = Mail::sent(ParticipantOtp::class)->first();
        $this->assertNotNull($mail);
        $row = DB::table('otp_challenges')->where('user_id', $u->id)->first();
        $this->assertTrue(Hash::check($mail->code, $row->code_hash));
        $this->get('/login')->assertDontSee($mail->code);
        $this->post('/otp/verify', ['email' => $u->email, 'code' => $mail->code])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($u);
        $this->get('/dashboard')->assertOk();
        $this->post('/logout');
        $this->post('/otp/verify', ['email' => $u->email, 'code' => $mail->code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }
}
