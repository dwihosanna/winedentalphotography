<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Lepas koneksi MySQL sebelum pekerjaan lama tanpa query (kirim file, olah gambar, render PDF).
 * Hosting membatasi koneksi bersamaan (lihat RetryingMySqlConnector); query berikutnya menyambung ulang otomatis.
 * SQLite tidak dilepas: database :memory: (tes) akan hilang bila koneksinya ditutup.
 */
final class ReleaseDatabase
{
    public static function now(): void
    {
        $connection = DB::connection();
        if ($connection->getDriverName() === 'mysql' && $connection->transactionLevel() === 0) {
            DB::disconnect();
        }
    }
}
