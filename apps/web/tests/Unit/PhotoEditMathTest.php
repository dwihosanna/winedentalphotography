<?php

namespace Tests\Unit;

use App\Support\PhotoEdit;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestImages;

/** Geometri luruskan dan urutan operasi PhotoEdit; harus cocok dengan pratinjau resources/js/editor.js. */
class PhotoEditMathTest extends TestCase
{
    public function test_inscribed_rectangle_keeps_aspect_and_shrinks_with_angle(): void
    {
        $this->assertSame([400, 300], PhotoEdit::inscribed(400, 300, 0));

        [$w, $h] = PhotoEdit::inscribed(400, 300, 10);
        $this->assertEqualsWithDelta(4 / 3, $w / $h, 0.01);
        $this->assertLessThan(400, $w);
        $this->assertSame(PhotoEdit::inscribed(400, 300, 10), PhotoEdit::inscribed(400, 300, -10));
    }

    public function test_straightened_render_has_no_empty_corners(): void
    {
        $params = PhotoEdit::normalize(['straighten' => 8]);
        $out = PhotoEdit::render(TestImages::jpeg(w: 200, h: 150, split: true), $params);

        [$w, $h] = getimagesizefromstring($out);
        $this->assertSame(PhotoEdit::inscribed(200, 150, 8), [$w, $h]);
        // Sudut tidak putih (latar rotasi) melainkan isi foto.
        $img = imagecreatefromstring($out);
        foreach ([[1, 1], [$w - 2, 1], [1, $h - 2], [$w - 2, $h - 2]] as [$x, $y]) {
            $rgb = imagecolorat($img, $x, $y);
            $this->assertLessThan(200, min(($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF));
        }
    }

    public function test_crop_keeps_original_pixels_without_resampling(): void
    {
        // Garis vertikal selebar 1 piksel: diperkecil/diperbesar sedikit saja garisnya langsung kabur jadi abu-abu.
        $src = imagecreatetruecolor(400, 300);
        for ($x = 0; $x < 400; $x++) {
            imageline($src, $x, 0, $x, 299, $x % 2 ? 0xFFFFFF : 0x000000);
        }
        ob_start();
        imagepng($src);
        $png = ob_get_clean();

        $out = PhotoEdit::render($png, PhotoEdit::normalize(['crop' => ['x' => 0.1025, 'y' => 0.2, 'w' => 0.25, 'h' => 0.25]]));

        $this->assertSame([100, 75], array_slice(getimagesizefromstring($out), 0, 2));
        $img = imagecreatefromstring($out);
        for ($x = 1; $x < 99; $x++) {
            // Kolom 0 hasil = kolom 41 asli (ganjil = putih).
            $luma = imagecolorat($img, $x, 37) & 0xFF;
            $x % 2 ? $this->assertLessThan(60, $luma) : $this->assertGreaterThan(195, $luma);
        }
    }

    public function test_rotate_is_applied_before_flip(): void
    {
        // 90° searah jarum jam: atas (merah) pindah ke kanan; lalu balik horizontal: merah ke kiri.
        $out = PhotoEdit::render(TestImages::jpeg(w: 80, h: 60, split: true), PhotoEdit::normalize(['rotate' => 90, 'flip_h' => true]));

        $this->assertSame('red', TestImages::colorAt($out, 5, 40));
        $this->assertSame('blue', TestImages::colorAt($out, 55, 40));
    }

    public function test_exif_orientation_is_applied_before_edits(): void
    {
        // Orientasi 6 = tampil diputar 90° searah jarum jam; tanpa edit lain, flip_v membalik tampilan itu.
        $out = PhotoEdit::render(TestImages::jpeg(w: 80, h: 60, orientation: 6, split: true), PhotoEdit::normalize(['flip_v' => true]));

        $this->assertSame([60, 80], array_slice(getimagesizefromstring($out), 0, 2));
        $this->assertSame('blue', TestImages::colorAt($out, 5, 40));
        $this->assertSame('red', TestImages::colorAt($out, 55, 40));
    }
}
