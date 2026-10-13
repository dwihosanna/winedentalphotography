<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Akun dan workspace (SRS 5.3, Bagian 7). Workspace = unit isolasi data pasien.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('practice_name');
            $table->foreignId('owner_user_id')->constrained('users');
            $table->string('plan', 16)->default('free'); // free | premium
            $table->unsignedInteger('patient_limit')->nullable(); // null = tanpa batas
            $table->unsignedBigInteger('storage_limit_bytes')->nullable(); // null = tanpa batas
            $table->unsignedBigInteger('used_bytes')->default(0);
            $table->string('status', 16)->default('active'); // active | suspended (hanya baca)
            $table->foreignId('plan_changed_by')->nullable()->constrained('users');
            $table->timestamp('plan_changed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('workspace_members', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('workspace_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('role', 16); // dentist | assistant
            $table->foreignId('invited_by')->nullable()->constrained('users');
            $table->string('status', 16)->default('active'); // invited | active | revoked
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id']);
        });

        Schema::create('terms_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('terms_version', 32);
            $table->boolean('dentist_declaration'); // pernyataan dokter gigi berizin
            $table->timestamp('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
        });

        Schema::create('dentist_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('str_number', 64);
            $table->string('str_file_path');
            $table->string('sip_file_path')->nullable();
            $table->string('status', 16)->default('pending'); // pending | approved | rejected
            $table->text('reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dentist_verifications');
        Schema::dropIfExists('terms_acceptances');
        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('workspaces');
    }
};
