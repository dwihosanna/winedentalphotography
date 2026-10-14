<?php

namespace App\Support;

use GdImage;

/**
 * Cetak timestamp di pojok kanan bawah foto (untuk PDF report; foto di aplikasi memakai label HTML).
 * Dipakai pada foto yang sudah diperkecil (±1100 px), jadi tambahannya hanya beberapa milidetik per foto.
 */
final class PhotoStamp
{
    public static function apply(string $jpeg, string $text, int $quality = 85): string
    {
        $image = @imagecreatefromstring($jpeg);
        if (! $image instanceof GdImage) {
            return $jpeg;
        }

        $font = config('dental.timestamp_font');
        $width = imagesx($image);
        $size = max(10, (int) round($width * 0.024));
        $pad = (int) round($size * 0.5);
        $box = imagettfbbox($size, 0, $font, $text);
        $textWidth = $box[2] - $box[0];
        $textHeight = $box[1] - $box[7];
        $x = $width - $textWidth - 3 * $pad;
        $baseline = imagesy($image) - 3 * $pad;

        // Latar gelap semi-transparan agar terbaca di gigi putih maupun gusi.
        imagealphablending($image, true);
        imagefilledrectangle($image, $x - $pad, $baseline - $textHeight - $pad, $x + $textWidth + $pad, $baseline + $pad,
            imagecolorallocatealpha($image, 0, 0, 0, 50));
        imagettftext($image, $size, 0, $x, $baseline, imagecolorallocate($image, 255, 255, 255), $font, $text);

        ob_start();
        imagejpeg($image, null, $quality);

        return ob_get_clean();
    }
}
