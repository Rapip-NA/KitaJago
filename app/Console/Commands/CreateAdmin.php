<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'portal:admin';

    protected $description = 'Buat admin baru secara interaktif, tanpa password bawaan';

    public function handle(): int
    {
        $name = $this->ask('Nama admin');
        $email = strtolower(trim((string) $this->ask('Email admin')));
        $password = $this->secret('Password admin (minimal 12 karakter)');
        $confirm = $this->secret('Ulangi password');
        $v = Validator::make(['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirm], ['name' => 'required|string|max:120', 'email' => 'required|email:rfc|max:254|unique:users,email', 'password' => 'required|string|min:12|max:1024|confirmed']);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $user = new User;
        $user->name = $name;
        $user->email = $email;
        $user->password = $password;
        $user->role = 'admin';
        $user->active = true;
        $user->save();
        $this->info('Admin berhasil dibuat. Masuk melalui /admin/login.');

        return self::SUCCESS;
    }
}
