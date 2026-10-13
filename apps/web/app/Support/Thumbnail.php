<?php

namespace App\Support;

use GdImage;

/** Thumbnail JPEG untuk galeri (NFR-02). Orientasi EXIF diterapkan karena thumbnail tidak membawa EXIF. */
final class Thumbnail
{
    public static function make(string $bytes, int $orientation, int $size, int $quality = 80): ?string
    {
        $previous = ini_get('memory_limit');
        ini_set('memory_limit', '1024M'); // foto 12 MP butuh ±50 MB di GD; 48 MP ±200 MB

        try {
            $image = @imagecreatefromstring($bytes);
            if (! $image instanceof GdImage) {
                return null;
            }
            $image = self::orient($image, $orientation);

            $w = imagesx($image);
            $h = imagesy($image);
            $scale = min(1, $size / max($w, $h));
            $tw = max(1, (int) round($w * $scale));
            $th = max(1, (int) round($h * $scale));
            $thumb = imagecreatetruecolor($tw, $th);
            imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255)); // PNG transparan → latar putih
            imagecopyresampled($thumb, $image, 0, 0, 0, 0, $tw, $th, $w, $h);

            ob_start();
            imagejpeg($thumb, null, $quality);

            return ob_get_clean();
        } finally {
            ini_set('memory_limit', $previous);
        }
    }

    /** Terapkan orientasi EXIF ke piksel (GD tidak membacanya sendiri). */
    public static function orient(GdImage $image, int $orientation): GdImage
    {
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, $orientation === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        }

        return match ($orientation) {
            3, 4 => imagerotate($image, 180, 0),
            5, 6 => imagerotate($image, -90, 0),
            7, 8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }
}
