<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function store(Request $request)
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['name' => 'required|string|max:120', 'email' => 'required|email:rfc|max:254', 'whatsapp' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ()-]{7,32}$/'], 'consent' => 'accepted']);
        Lead::firstOrCreate(['email' => $data['email']], ['name' => $data['name'], 'whatsapp' => $data['whatsapp'] ?? null, 'consented_at' => now()]);

        return redirect('/terima-kasih');
    }
}
