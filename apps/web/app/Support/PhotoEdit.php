<?php

namespace App\Support;

use GdImage;
use Illuminate\Validation\ValidationException;

/**
 * Edit non-destruktif (FR-13, FR-14): parameter disimpan di photos.edit_params, file asli tidak disentuh.
 * Urutan operasi harus sama dengan pratinjau di resources/js/editor.js:
 * orientasi EXIF → putar 90° → balik → luruskan (lalu potong ke persegi panjang terbesar tanpa sudut kosong) → crop.
 * Crop dinyatakan relatif (0–1) terhadap gambar hasil langkah sebelumnya.
 */
final class PhotoEdit
{
    public const MAX_STRAIGHTEN = 15;

    public const JPEG_QUALITY = 95;

    /**
     * @param  array<string, mixed>  $input
     * @return array{rotate: int, flip_h: bool, flip_v: bool, straighten: float, crop: array{x: float, y: float, w: float, h: float}|null, auto_flip: bool}
     */
    public static function normalize(array $input): array
    {
        $rotate = (int) ($input['rotate'] ?? 0);
        $straighten = (float) ($input['straighten'] ?? 0);
        if ($rotate % 90 !== 0 || abs($straighten) > self::MAX_STRAIGHTEN) {
            throw ValidationException::withMessages(['params' => 'Parameter edit tidak valid.']);
        }

        $crop = null;
        if (is_array($input['crop'] ?? null)) {
            $c = array_map(fn ($v) => round(min(1, max(0, (float) $v)), 5), array_intersect_key($input['crop'], array_flip(['x', 'y', 'w', 'h'])));
            if (count($c) !== 4 || $c['w'] < 0.05 || $c['h'] < 0.05 || $c['x'] + $c['w'] > 1.00001 || $c['y'] + $c['h'] > 1.00001) {
                throw ValidationException::withMessages(['params' => 'Area crop tidak valid.']);
            }
            // Crop seluas gambar = tanpa crop.
            $crop = ($c['w'] > 0.999 && $c['h'] > 0.999) ? null : ['x' => $c['x'], 'y' => $c['y'], 'w' => $c['w'], 'h' => $c['h']];
        }

        return [
            'rotate' => (($rotate % 360) + 360) % 360,
            'flip_h' => (bool) ($input['flip_h'] ?? false),
            'flip_v' => (bool) ($input['flip_v'] ?? false),
            'straighten' => round($straighten, 1),
            'crop' => $crop,
            'auto_flip' => (bool) ($input['auto_flip'] ?? false),
        ];
    }

    /** @param  array<string, mixed>  $params  hasil normalize() */
    public static function isIdentity(array $params): bool
    {
        return $params['rotate'] === 0 && ! $params['flip_h'] && ! $params['flip_v']
            && $params['straighten'] == 0 && $params['crop'] === null;
    }

    /**
     * Render hasil edit dari byte file asli. Profil warna ICC tidak ikut (GD), jadi file asli tetap acuan klinis.
     *
     * @param  array<string, mixed>  $params  hasil normalize()
     */
    public static function render(string $originalBytes, array $params): string
    {
        $previous = ini_get('memory_limit');
        ini_set('memory_limit', '1024M');

        try {
            $image = @imagecreatefromstring($originalBytes);
            if (! $image instanceof GdImage) {
                throw ValidationException::withMessages(['params' => 'Foto ini tidak bisa diedit.']);
            }
            $image = Thumbnail::orient($image, JpegMetadata::orientationOf($originalBytes));

            if ($params['rotate']) {
                $image = imagerotate($image, -$params['rotate'], 0); // GD memutar berlawanan jarum jam
            }
            if ($params['flip_h'] && $params['flip_v']) {
                imageflip($image, IMG_FLIP_BOTH);
            } elseif ($params['flip_h'] || $params['flip_v']) {
                imageflip($image, $params['flip_h'] ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
            }
            if ($params['straighten'] != 0) {
                $image = self::straighten($image, $params['straighten']);
            }
            if ($params['crop'] !== null) {
                $w = imagesx($image);
                $h = imagesy($image);
                $c = $params['crop'];
                $image = imagecrop($image, [
                    'x' => (int) round($c['x'] * $w), 'y' => (int) round($c['y'] * $h),
                    'width' => max(1, (int) round($c['w'] * $w)), 'height' => max(1, (int) round($c['h'] * $h)),
                ]);
            }

            // Crop memotong piksel asli 1:1 (tanpa diperkecil/diperbesar); kualitas tinggi agar hasil tetap tajam saat di-zoom.
            ob_start();
            imagejpeg($image, null, self::JPEG_QUALITY);

            return ob_get_clean();
        } finally {
            ini_set('memory_limit', $previous);
        }
    }

    /** Putar searah jarum jam sebesar $degrees lalu ambil persegi panjang terbesar (rasio sama) tanpa sudut kosong. */
    private static function straighten(GdImage $image, float $degrees): GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        [$cw, $ch] = self::inscribed($w, $h, $degrees);

        // Interpolasi tidak ikut ke gambar baru hasil putar/orientasi sebelumnya, jadi diset di sini.
        imagesetinterpolation($image, IMG_BICUBIC);
        $rotated = imagerotate($image, -$degrees, imagecolorallocate($image, 255, 255, 255));

        return imagecrop($rotated, [
            'x' => (int) floor((imagesx($rotated) - $cw) / 2), 'y' => (int) floor((imagesy($rotated) - $ch) / 2),
            'width' => $cw, 'height' => $ch,
        ]);
    }

    /** @return array{0: int, 1: int} ukuran persegi panjang rasio sama yang muat di dalam gambar w×h yang diputar */
    public static function inscribed(int $w, int $h, float $degrees): array
    {
        $a = deg2rad(abs($degrees));
        $k = min($w / ($w * cos($a) + $h * sin($a)), $h / ($w * sin($a) + $h * cos($a)));

        return [max(1, (int) floor($w * $k)), max(1, (int) floor($h * $k))];
    }
}
