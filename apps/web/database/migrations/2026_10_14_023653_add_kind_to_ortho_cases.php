<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Kasus non-ortho (tambalan, scaling, dll.): jenis kasus + jenis perawatan; tipe behel hanya untuk ortho. */
    public function up(): void
    {
        Schema::table('ortho_cases', function (Blueprint $table) {
            $table->string('kind', 16)->default('ortho')->after('patient_id'); // ortho | general
            $table->string('treatment_type', 64)->nullable()->after('brace_type');
            $table->string('brace_type', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ortho_cases', function (Blueprint $table) {
            $table->dropColumn(['kind', 'treatment_type']);
        });
    }
};
