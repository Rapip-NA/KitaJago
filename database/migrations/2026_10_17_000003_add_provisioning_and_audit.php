<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $t) {
            $t->foreignId('verified_by')->nullable()->constrained('users');
            $t->timestamp('verified_at')->nullable();
            $t->foreignId('user_id')->nullable()->unique()->constrained('users');
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->constrained('users');
            $t->foreignId('lead_id')->constrained('leads');
            $t->string('action');
            $t->string('from_status')->nullable();
            $t->string('to_status')->nullable();
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::table('leads', fn (Blueprint $t) => $t->dropColumn(['verified_by', 'verified_at', 'user_id']));
    }
};
