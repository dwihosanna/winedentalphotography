<?php

namespace Tests\Unit;

use App\Support\JpegMetadata;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestImages;

class JpegMetadataTest extends TestCase
{
    private function tmp(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'jpg');
        file_put_contents($path, $bytes);

        return $path;
    }

    public function test_reads_orientation_date_and_detects_gps(): void
    {
        $path = $this->tmp(TestImages::jpeg(orientation: 6, date: '2024:05:01 10:20:30', gps: true));

        $meta = JpegMetadata::read($path);
        $this->assertSame(6, $meta['orientation']);
        $this->assertSame('2024-05-01 10:20:30', $meta['taken_at']);
        $this->assertArrayHasKey('GPSLatitudeRef', exif_read_data($path, 'GPS') ?: []);
    }

    public function test_strip_removes_gps_and_keeps_orientation_without_reencoding(): void
    {
        $original = TestImages::jpeg(orientation: 6, date: '2024:05:01 10:20:30', gps: true);
        $stripped = JpegMetadata::strip($original, 6);
        $path = $this->tmp($stripped);

        $exif = exif_read_data($path) ?: [];
        $this->assertSame(6, $exif['Orientation']);
        $this->assertArrayNotHasKey('GPSLatitudeRef', $exif);
        $this->assertArrayNotHasKey('DateTime', $exif);
        $this->assertNotFalse(imagecreatefromstring($stripped));

        // Data gambar (setelah Start of Scan) identik byte per byte: tidak ada re-encode.
        $this->assertSame(substr($original, strpos($original, "\xFF\xDA")), substr($stripped, strpos($stripped, "\xFF\xDA")));
    }

    public function test_orientation_1_leaves_no_exif(): void
    {
        $stripped = JpegMetadata::strip(TestImages::jpeg(gps: true), 1);

        $this->assertFalse(@exif_read_data($this->tmp($stripped), 'IFD0'));
    }

    public function test_rejects_non_jpeg(): void
    {
        $this->assertNull(JpegMetadata::strip('not a jpeg'));
    }
}
