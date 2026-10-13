<?php

namespace App\Support;

/**
 * Dental Photo ID (SRS 5.2): DP-XXXX-XXXX = 7 karakter acak base32 Crockford + 1 checksum.
 * Checksum memakai Luhn mod 32, yang menangkap semua salah ketik satu karakter dan
 * hampir semua pertukaran dua karakter bersebelahan.
 */
final class Dpid
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'; // tanpa I, L, O, U

    public static function generate(): string
    {
        $body = '';
        for ($i = 0; $i < 7; $i++) {
            $body .= self::ALPHABET[random_int(0, 31)];
        }

        return self::format($body.self::checkChar($body));
    }

    public static function isValid(string $dpid): bool
    {
        $raw = self::normalize($dpid);

        return $raw !== null && self::checkChar(substr($raw, 0, 7)) === $raw[7];
    }

    /** Terima ketikan bebas (huruf kecil, tanpa strip, O/I/L) lalu kembalikan format baku, atau null. */
    public static function normalize(string $input): ?string
    {
        $s = strtr(strtoupper(preg_replace('/[\s-]/', '', $input)), ['O' => '0', 'I' => '1', 'L' => '1']);
        if (strlen($s) === 10 && str_starts_with($s, 'DP')) {
            $s = substr($s, 2);
        }

        return preg_match('/^['.self::ALPHABET.']{8}$/', $s) ? $s : null;
    }

    public static function format(string $raw): string
    {
        return 'DP-'.substr($raw, 0, 4).'-'.substr($raw, 4, 4);
    }

    private static function checkChar(string $body): string
    {
        $sum = 0;
        $double = true;
        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $v = strpos(self::ALPHABET, $body[$i]);
            if ($double) {
                $v *= 2;
                $v = intdiv($v, 32) + $v % 32;
            }
            $sum += $v;
            $double = ! $double;
        }

        return self::ALPHABET[(32 - $sum % 32) % 32];
    }
}
