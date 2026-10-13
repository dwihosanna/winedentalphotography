<?php

namespace App\Support;

/**
 * Membersihkan metadata JPEG tanpa re-encode (kualitas foto klinis tidak turun).
 * Segmen APP1 (EXIF + XMP, termasuk lokasi GPS) dan APP13 (IPTC) dibuang; APP0 (JFIF) dan APP2 (profil warna ICC,
 * mis. Display P3 iPhone) dipertahankan. Orientasi EXIF ditulis ulang dalam APP1 minimal agar foto tetap tegak.
 */
final class JpegMetadata
{
    /** @return array{orientation: int, taken_at: ?string, model: ?string} */
    public static function read(string $path): array
    {
        $exif = @exif_read_data($path) ?: [];
        $date = $exif['DateTimeOriginal'] ?? $exif['DateTime'] ?? null;

        return [
            'orientation' => (int) ($exif['Orientation'] ?? 1),
            'taken_at' => $date && preg_match('/^\d{4}:\d{2}:\d{2} \d{2}:\d{2}:\d{2}$/', $date)
                ? preg_replace('/^(\d{4}):(\d{2}):(\d{2})/', '$1-$2-$3', $date) : null,
            'model' => isset($exif['Model']) ? trim((string) $exif['Model']) : null,
        ];
    }

    /** Kembalikan byte JPEG tanpa APP1/APP13; null bila bukan JPEG yang bisa diurai. */
    public static function strip(string $bytes, int $orientation = 1): ?string
    {
        if (strncmp($bytes, "\xFF\xD8", 2) !== 0) {
            return null;
        }

        $out = "\xFF\xD8";
        $pos = 2;
        $len = strlen($bytes);
        $orientationWritten = $orientation === 1;

        while ($pos + 4 <= $len) {
            if ($bytes[$pos] !== "\xFF") {
                return null;
            }
            $marker = ord($bytes[$pos + 1]);

            if ($marker === 0xFF) { // byte pengisi
                $pos++;

                continue;
            }
            if ($marker === 0xDA) { // Start of Scan: sisa file adalah data gambar
                if (! $orientationWritten) {
                    $out .= self::orientationSegment($orientation);
                }

                return $out.substr($bytes, $pos);
            }

            $segLen = unpack('n', substr($bytes, $pos + 2, 2))[1];
            if ($segLen < 2 || $pos + 2 + $segLen > $len) {
                return null;
            }
            $segment = substr($bytes, $pos, 2 + $segLen);
            $pos += 2 + $segLen;

            if ($marker === 0xE1 || $marker === 0xED) {
                continue;
            }
            // APP1 orientasi diletakkan setelah APP0/JFIF, sebelum segmen lain.
            if (! $orientationWritten && $marker !== 0xE0) {
                $out .= self::orientationSegment($orientation);
                $orientationWritten = true;
            }
            $out .= $segment;
        }

        return null;
    }

    /** APP1 Exif minimal: TIFF big-endian, IFD0 berisi satu tag Orientation (0x0112). */
    private static function orientationSegment(int $orientation): string
    {
        $tiff = 'MM'.pack('n', 42).pack('N', 8)
            .pack('n', 1).pack('n', 0x0112).pack('n', 3).pack('N', 1).pack('n', $orientation).pack('n', 0)
            .pack('N', 0);
        $payload = "Exif\0\0".$tiff;

        return "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
    }
}
