<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_is_created_interactively_without_shared_password(): void
    {
        $password = bin2hex(random_bytes(16));
        $this->artisan('portal:admin')->expectsQuestion('Nama admin', 'Operator')->expectsQuestion('Email admin', ' ADMIN@EXAMPLE.TEST ')->expectsQuestion('Password admin (minimal 12 karakter)', $password)->expectsQuestion('Ulangi password', $password)->assertSuccessful();
        $u = User::where('email', 'admin@example.test')->firstOrFail();
        $this->assertSame('admin', $u->role);
        $this->assertTrue($u->active);
        $this->assertTrue(Hash::check($password, $u->password));
    }
}
