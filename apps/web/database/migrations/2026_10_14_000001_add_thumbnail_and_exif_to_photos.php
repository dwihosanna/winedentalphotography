<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->string('thumb_path')->nullable()->after('edited_path'); // tidak dihitung kuota (FR-34)
            $table->timestamp('exif_taken_at')->nullable()->after('taken_at'); // tanggal asli foto unggahan
            $table->string('original_name')->nullable()->after('source'); // nama file unggahan, untuk dikenali dokter
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn(['thumb_path', 'exif_taken_at', 'original_name']);
        });
    }
};
