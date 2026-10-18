<?php

namespace App\Http\Controllers;

use App\Mail\ParticipantOtp;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class OtpController extends Controller
{
    private const MESSAGE = 'Jika akun memenuhi syarat, permintaan kode diproses. Periksa email/spam atau hubungi admin jika kode belum diterima.';

    private function email(Request $r): string
    {
        $r->merge(['email' => strtolower(trim((string) $r->input('email')))]);

        return $r->validate(['email' => 'required|email:rfc|max:254'])['email'];
    }

    public function request(Request $r)
    {
        $email = $this->email($r);
        if (! config('portal.smtp_ready') || config('mail.default') !== 'smtp' || ! config('mail.mailers.smtp.host')) {
            return back()->withErrors(['email' => 'Layanan email belum dikonfigurasi. Hubungi admin untuk pengaturan SMTP.']);
        }
        $key = 'otp-send:'.hash('sha256', $email);
        $ip = 'otp-send-ip:'.hash('sha256', $r->ip());
        if (! RateLimiter::tooManyAttempts($key, config('portal.otp_send_email_limit', 5)) && ! RateLimiter::tooManyAttempts($ip, config('portal.otp_send_ip_limit', 20))) {
            RateLimiter::hit($key, 3600);
            RateLimiter::hit($ip, 3600);
            try {
                Cache::lock('otp:'.hash('sha256', $email), 30)->block(5, function () use ($email) {
                    $u = User::where('email', $email)->first();
                    if (! $u || ! $u->eligible()) {
                        return;
                    }
                    $old = DB::table('otp_challenges')->where('user_id', $u->id)->first();
                    if ($old && Carbon::parse($old->sent_at)->addSeconds(config('portal.otp_resend_seconds', 60))->isFuture()) {
                        return;
                    }
                    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    DB::table('otp_challenges')->updateOrInsert(['user_id' => $u->id], ['code_hash' => Hash::make($code), 'access_version' => $u->access_version, 'attempts' => 0, 'expires_at' => now()->addMinutes(config('portal.otp_minutes', 10)), 'sent_at' => now(), 'consumed_at' => null]);
                    try {
                        Mail::to($email)->send(new ParticipantOtp($code));
                    } catch (\Throwable $e) {
                        DB::table('otp_challenges')->where('user_id', $u->id)->update(['consumed_at' => now()]);
                        // Never log transport exception: SMTP diagnostics may contain message content.
                        Log::warning('Pengiriman OTP gagal. Periksa koneksi dan konfigurasi SMTP.');
                    }
                });
            } catch (LockTimeoutException $e) { /* Generic retry guidance, no account disclosure. */
            }
        }

        return redirect('/login')->with('status', self::MESSAGE)->with('otp_email', $email);
    }

    public function verify(Request $r)
    {
        $email = $this->email($r);
        $code = $r->validate(['code' => 'required|digits:6'])['code'];
        $keys = ['otp-verify-email:'.hash('sha256', $email), 'otp-verify-ip:'.hash('sha256', $r->ip())];
        foreach ($keys as $index => $key) {
            if (RateLimiter::tooManyAttempts($key, config($index === 0 ? 'portal.otp_verify_email_limit' : 'portal.otp_verify_ip_limit', 30))) {
                return $this->invalid();
            }
        }
        foreach ($keys as $key) {
            RateLimiter::hit($key, 300);
        }
        $user = null;
        try {
            $user = Cache::lock('otp:'.hash('sha256', $email), 30)->block(5, function () use ($email, $code) {
                $u = User::where('email', $email)->first();
                if (! $u || ! $u->eligible()) {
                    return null;
                }
                $q = DB::table('otp_challenges')->where('user_id', $u->id)->whereNull('consumed_at')->where('expires_at', '>', now())->where('access_version', $u->access_version)->where('attempts', '<', config('portal.otp_max_attempts', 5));
                $row = (clone $q)->first();
                if (! $row) {
                    return null;
                }
                if (! $q->increment('attempts')) {
                    return null;
                }
                if (! Hash::check($code, $row->code_hash)) {
                    return null;
                }
                // Compare-and-set consumption protects replay even if lock lease expires.
                $changed = DB::table('otp_challenges')->where('id', $row->id)->where('code_hash', $row->code_hash)->whereNull('consumed_at')->update(['consumed_at' => now()]);

                return $changed ? $u : null;
            });
        } catch (LockTimeoutException $e) {
        }
        if (! $user) {
            return $this->invalid();
        }
        Auth::login($user);
        $r->session()->regenerate();
        $r->session()->put('access_version', $user->access_version);

        return redirect('/dashboard');
    }

    private function invalid()
    {
        return redirect('/login')->withErrors(['code' => 'Kode tidak valid atau tidak tersedia. Minta kode baru atau hubungi admin.']);
    }
}
