<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadAdminController extends Controller
{
    public function index(Request $r)
    {
        $data = $r->validate(['status' => 'nullable|in:pending,verified,rejected', 'q' => 'nullable|string|max:120']);
        $leads = Lead::query()->when($data['status'] ?? null, fn ($q, $s) => $q->where('payment_status', $s))->when($data['q'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('email', 'like', '%'.$s.'%')->orWhere('name', 'like', '%'.$s.'%')))->latest('id')->paginate(20)->withQueryString();

        return view('admin.leads', compact('leads'));
    }

    public function show(Lead $lead)
    {
        $user = $lead->user_id ? User::find($lead->user_id) : null;
        $events = DB::table('audit_events')->where('lead_id', $lead->id)->orderByDesc('id')->get();

        return view('admin.lead', compact('lead', 'user', 'events'));
    }

    public function payment(Request $r, Lead $lead)
    {
        $status = $r->validate(['status' => 'required|in:pending,verified,rejected'])['status'];
        DB::transaction(function () use ($r, $lead, $status) {
            $lead = Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            if ($lead->payment_status === $status) {
                return;
            }
            $old = $lead->payment_status;
            if ($status !== 'verified' && $lead->user_id) {
                User::whereKey($lead->user_id)->update(['active' => false, 'access_version' => DB::raw('access_version + 1')]);
            }
            $lead->update(['payment_status' => $status, 'verified_by' => $status === 'verified' ? $r->user()->id : null, 'verified_at' => $status === 'verified' ? now() : null]);
            $this->audit($r, $lead, 'payment', $old, $status);
        });

        return back()->with('status', 'Status pembayaran diperbarui. Penyiapan akun tetap tindakan terpisah.');
    }

    public function provision(Request $r, Lead $lead)
    {
        DB::transaction(function () use ($r, $lead) {
            $lead = Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            abort_unless($lead->payment_status === 'verified', 422, 'Pembayaran harus terverifikasi.');
            $user = User::where('email', $lead->email)->lockForUpdate()->first();
            abort_if($user && ($user->role !== 'participant' || $lead->user_id !== $user->id), 409, 'Email sudah digunakan akun lain.');
            if ($user && $user->active) {
                return;
            }
            if (! $user) {
                $user = new User;
                $user->name = $lead->name;
                $user->email = $lead->email;
                $user->password = Str::random(64);
                $user->role = 'participant';
            }
            $user->active = true;
            $user->save();
            $lead->update(['user_id' => $user->id]);
            $this->audit($r, $lead, 'provision');
        });

        return back()->with('status', 'Akun peserta siap. Peserta dapat meminta OTP melalui halaman masuk.');
    }

    public function access(Request $r, Lead $lead)
    {
        $active = $r->validate(['active' => 'required|boolean'])['active'];
        DB::transaction(function () use ($r, $lead, $active) {
            $lead = Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            abort_if($active && $lead->payment_status !== 'verified', 422, 'Pembayaran belum valid.');
            $user = User::whereKey($lead->user_id)->lockForUpdate()->first();
            abort_unless($user && $user->role === 'participant' && $user->email === $lead->email, 422);
            if ($user->active === (bool) $active) {
                return;
            }
            $user->active = (bool) $active;
            $user->access_version++;
            $user->save();
            $this->audit($r, $lead, $active ? 'enable' : 'disable');
        });

        return back()->with('status', 'Status akses diperbarui.');
    }

    private function audit(Request $r, Lead $lead, string $action, ?string $from = null, ?string $to = null): void
    {
        DB::table('audit_events')->insert(['actor_id' => $r->user()->id, 'lead_id' => $lead->id, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'created_at' => now()]);
    }
}
