<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings', ['settings' => DB::table('portal_settings')->find(1)]);
    }

    public function update(Request $r)
    {
        $data = $r->validate(['whatsapp_url' => 'nullable|url:http,https|max:2048', 'zoom_url' => 'nullable|url:http,https|max:2048', 'material_url' => 'nullable|url:http,https|max:2048']);
        DB::table('portal_settings')->updateOrInsert(['id' => 1], [...array_fill_keys(['whatsapp_url', 'zoom_url', 'material_url'], null), ...$data, 'updated_by' => $r->user()->id, 'updated_at' => now()]);

        return redirect('/admin/settings')->with('status', 'Tautan portal diperbarui.');
    }

    public function dashboard()
    {
        return view('dashboard', ['settings' => DB::table('portal_settings')->find(1)]);
    }
}
