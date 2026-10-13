<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Rantai Pasien → Kasus → Sesi → Foto (SRS Bagian 7).
// MySQL tidak punya row-level security, jadi setiap tabel data pasien membawa workspace_id dan merujuk
// induknya lewat foreign key gabungan (workspace_id, id): baris anak tidak bisa menunjuk induk dari workspace lain.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained();
            $table->char('dpid', 12)->unique(); // DP-XXXX-XXXX, unik global, tidak pernah berubah (SRS 5.2)
            $table->string('name');
            $table->date('birth_date');
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('sex', 8)->nullable(); // opsional
            $table->uuid('avatar_photo_id')->nullable();
            $table->boolean('is_demo')->default(false); // pasien demo tidak dihitung kuota (FR-34)
            $table->timestamps();
            $table->unique(['workspace_id', 'id']);
            $table->index(['workspace_id', 'name']);
            $table->index(['workspace_id', 'name', 'birth_date']); // cek kemungkinan duplikat (FR-02)
        });

        Schema::create('patient_external_ids', function (Blueprint $table) {
            $table->id();
            $table->uuid('workspace_id');
            $table->uuid('patient_id');
            $table->string('system', 64); // mis. rme_klinik_x
            $table->string('value', 64); // No. RM
            $table->string('source', 16)->default('manual'); // manual | sync
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'system', 'value']);
            $table->foreign(['workspace_id', 'patient_id'])->references(['workspace_id', 'id'])->on('patients');
        });

        Schema::create('ortho_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('patient_id');
            $table->string('brace_type', 64);
            $table->date('installed_on')->nullable();
            $table->date('removed_on')->nullable();
            $table->foreignId('dentist_user_id')->constrained('users');
            $table->string('status', 16)->default('active'); // active | finished
            $table->timestamps();
            $table->unique(['workspace_id', 'id']);
            $table->foreign(['workspace_id', 'patient_id'])->references(['workspace_id', 'id'])->on('patients');
        });

        // Nama tabel bukan "sessions" karena dipakai sesi login Laravel.
        Schema::create('photo_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('ortho_case_id');
            $table->string('stage', 16); // initial | insertion | control | debond | retention
            $table->unsignedSmallInteger('control_number')->nullable(); // hanya untuk stage control
            $table->date('session_date');
            $table->text('notes')->nullable();
            $table->string('status', 16)->default('draft'); // draft | complete
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->unique(['workspace_id', 'id']);
            $table->index(['workspace_id', 'ortho_case_id', 'session_date']);
            $table->foreign(['workspace_id', 'ortho_case_id'])->references(['workspace_id', 'id'])->on('ortho_cases');
        });

        // Konfigurasi global (bukan per workspace): slot per tahap dan wajib/opsional (SRS 6.1).
        Schema::create('slot_templates', function (Blueprint $table) {
            $table->id();
            $table->string('stage', 16);
            $table->string('slot_code', 8);
            $table->string('label');
            $table->string('category', 32);
            $table->string('kind', 8); // photo | xray (xray hanya upload)
            $table->boolean('required');
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();
            $table->unique(['stage', 'slot_code']);
        });

        Schema::create('photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('photo_session_id');
            $table->string('slot_code', 8);
            $table->string('category', 32);
            $table->string('source', 8); // camera | upload
            // Disk + path, bukan URL: file bisa dipindah ke object storage tanpa mengubah baris ini selain kolom disk.
            $table->string('disk', 32);
            $table->string('original_path');
            $table->string('edited_path')->nullable();
            $table->json('edit_params')->nullable(); // crop/rotate/flip, non-destruktif (FR-13)
            $table->string('mime', 64);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('original_bytes');
            $table->unsignedBigInteger('edited_bytes')->default(0);
            $table->char('sha256', 64); // keutuhan file (Permenkes 24/2022)
            $table->string('device')->nullable();
            $table->string('lens_mode', 16)->nullable(); // main | multi | manual (dari capture)
            $table->decimal('zoom', 5, 2)->nullable();
            $table->string('overlay_version', 16)->nullable();
            $table->foreignId('taken_by')->constrained('users');
            $table->timestamp('taken_at');
            $table->uuid('replaced_by_photo_id')->nullable(); // retake: foto lama tetap ada (FR-10)
            $table->timestamps();
            $table->unique(['workspace_id', 'id']);
            $table->index(['workspace_id', 'photo_session_id', 'slot_code']);
            $table->foreign(['workspace_id', 'photo_session_id'])->references(['workspace_id', 'id'])->on('photo_sessions');
            $table->foreign(['workspace_id', 'replaced_by_photo_id'])->references(['workspace_id', 'id'])->on('photos');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->foreign(['workspace_id', 'avatar_photo_id'])->references(['workspace_id', 'id'])->on('photos');
        });

        Schema::create('consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('patient_id');
            $table->string('type', 16); // clinical | education | publication
            $table->date('given_on');
            $table->date('valid_until')->nullable();
            $table->timestamp('revoked_at')->nullable(); // publikasi bisa ditarik kembali
            $table->string('guardian_name')->nullable(); // pasien < 18 tahun
            $table->string('proof_disk', 32)->nullable();
            $table->string('proof_path')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();
            $table->foreign(['workspace_id', 'patient_id'])->references(['workspace_id', 'id'])->on('patients');
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('ortho_case_id');
            $table->string('template', 32);
            $table->uuid('before_session_id')->nullable();
            $table->uuid('after_session_id')->nullable();
            $table->json('selected_photos'); // {slot_code: photo_id}
            $table->boolean('anonymized')->default(false);
            $table->string('disk', 32)->nullable();
            $table->string('pdf_path')->nullable(); // snapshot, diisi job setelah PDF selesai
            $table->unsignedBigInteger('pdf_bytes')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->foreign(['workspace_id', 'ortho_case_id'])->references(['workspace_id', 'id'])->on('ortho_cases');
            $table->foreign(['workspace_id', 'before_session_id'])->references(['workspace_id', 'id'])->on('photo_sessions');
            $table->foreign(['workspace_id', 'after_session_id'])->references(['workspace_id', 'id'])->on('photo_sessions');
        });

        // Append-only: tidak ada updated_at; model menolak update/delete.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('workspace_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('action', 32); // view | create | update | upload | replace | export | plan_change | ...
            $table->string('entity_type', 64);
            $table->string('entity_id', 64)->nullable();
            $table->json('meta')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['workspace_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('consents');
        Schema::table('patients', fn (Blueprint $table) => $table->dropForeign(['workspace_id', 'avatar_photo_id']));
        Schema::dropIfExists('photos');
        Schema::dropIfExists('slot_templates');
        Schema::dropIfExists('photo_sessions');
        Schema::dropIfExists('ortho_cases');
        Schema::dropIfExists('patient_external_ids');
        Schema::dropIfExists('patients');
    }
};
