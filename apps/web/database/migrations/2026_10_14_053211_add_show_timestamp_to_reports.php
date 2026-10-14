<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Opsi cetak timestamp di foto PDF report (disimpan agar terlihat di detail report). */
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->boolean('show_timestamp')->default(false)->after('anonymized');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('show_timestamp');
        });
    }
};
