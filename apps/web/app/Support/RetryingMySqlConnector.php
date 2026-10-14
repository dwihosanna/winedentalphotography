<?php

namespace App\Support;

use Illuminate\Database\Connectors\MySqlConnector;
use PDO;
use PDOException;

/**
 * Hostinger Cloud Startup membatasi ±20 koneksi MySQL bersamaan per akun; koneksi berikutnya ditolak dengan
 * "[2002] Operation not permitted" (diuji 14 Okt 2026). Saat itu terjadi, tunggu sebentar lalu coba lagi
 * (backoff + jitter, total ±3 detik) agar lonjakan sesaat (mis. galeri memuat banyak thumbnail) tidak jadi error 500.
 */
class RetryingMySqlConnector extends MySqlConnector
{
    /** Jeda sebelum percobaan ke-n (ms); jitter ditambahkan agar request yang menunggu tidak bertabrakan lagi. */
    public const DELAYS_MS = [50, 100, 200, 300, 400, 500, 600, 700];

    protected function createPdoConnection($dsn, $username, #[\SensitiveParameter] $password, $options): PDO
    {
        foreach (self::DELAYS_MS as $delay) {
            try {
                return $this->connectOnce($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                if (! self::isConnectionLimit($e)) {
                    throw $e;
                }
                $this->pause($delay + random_int(0, $delay));
            }
        }

        return $this->connectOnce($dsn, $username, $password, $options);
    }

    /** Ditolak karena batas koneksi (bukan password salah, database tidak ada, dll.). */
    public static function isConnectionLimit(PDOException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, '[2002] Operation not permitted')
            || str_contains($message, '[1040]') // Too many connections
            || str_contains($message, '[1203]'); // max_user_connections
    }

    protected function connectOnce($dsn, $username, #[\SensitiveParameter] $password, $options): PDO
    {
        return parent::createPdoConnection($dsn, $username, $password, $options);
    }

    protected function pause(int $milliseconds): void
    {
        usleep($milliseconds * 1000);
    }
}
