<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_challenges', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('code_hash');
            $t->unsignedInteger('access_version');
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('sent_at');
            $t->timestamp('consumed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_challenges');
    }
};
