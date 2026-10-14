<?php

namespace Tests\Unit;

use App\Support\PhotoStamp;
use Tests\Support\TestImages;
use Tests\TestCase;

/** Timestamp tercetak di pojok kanan bawah foto PDF; ukuran foto tidak berubah. */
class PhotoStampTest extends TestCase
{
    public function test_stamp_is_drawn_bottom_right_and_keeps_size(): void
    {
        $jpeg = TestImages::jpeg(w: 1100, h: 825);
        $out = PhotoStamp::apply($jpeg, '14-10-2026 11:05');

        $this->assertSame([1100, 825], array_slice(getimagesizefromstring($out), 0, 2));
        $before = imagecreatefromstring($jpeg);
        $after = imagecreatefromstring($out);
        // Area kanan bawah berubah (latar gelap + teks putih), area kiri atas tetap.
        $changed = 0;
        for ($x = 850; $x < 1080; $x += 5) {
            for ($y = 760; $y < 815; $y += 5) {
                $changed += abs((imagecolorat($after, $x, $y) & 0xFF) - (imagecolorat($before, $x, $y) & 0xFF)) > 40 ? 1 : 0;
            }
        }
        $this->assertGreaterThan(50, $changed);
        $this->assertEqualsWithDelta(imagecolorat($before, 20, 20) & 0xFF, imagecolorat($after, 20, 20) & 0xFF, 8);
    }

    public function test_long_watermark_shrinks_to_stay_inside_photo(): void
    {
        $jpeg = TestImages::jpeg(w: 400, h: 300);
        $out = PhotoStamp::apply($jpeg, 'Raden Ayu Kusumawardhani Setiawati Putri - Kontrol ke-12 - 14-10-2026');

        $before = imagecreatefromstring($jpeg);
        $after = imagecreatefromstring($out);
        // Kolom paling kiri tidak tersentuh: teks tidak meluber keluar foto.
        for ($y = 0; $y < 300; $y += 3) {
            $this->assertEqualsWithDelta(imagecolorat($before, 1, $y) & 0xFF, imagecolorat($after, 1, $y) & 0xFF, 10);
        }
    }
}
