<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_settings', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
            $t->text('whatsapp_url')->nullable();
            $t->text('zoom_url')->nullable();
            $t->text('material_url')->nullable();
            $t->foreignId('updated_by')->nullable()->constrained('users');
            $t->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_settings');
    }
};
