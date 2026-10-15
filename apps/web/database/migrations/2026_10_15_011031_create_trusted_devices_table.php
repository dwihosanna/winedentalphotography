<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Perangkat tepercaya: login sekali, tetap masuk sampai kedaluwarsa. Token per perangkat (bukan remember_token
     * bersama), jadi keluar di satu perangkat tidak memaksa perangkat lain login ulang.
     */
    public function up(): void
    {
        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique(); // sha256 dari token di cookie; token asli tidak disimpan
            $table->string('name', 120);
            $table->timestamp('last_used_at');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
    }
};
