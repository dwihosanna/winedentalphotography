<?php

namespace App\Support;

/** Potongan nama file yang aman di iOS/Windows: karakter terlarang jadi spasi, spasi dirapikan, panjang dibatasi. */
final class SafeFileName
{
    public static function part(string $text, string $fallback = 'Pasien', int $max = 80): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', preg_replace('~[\\\\/:*?"<>|\x00-\x1F]~u', ' ', $text)));

        return mb_substr($clean, 0, $max) ?: $fallback;
    }
}
