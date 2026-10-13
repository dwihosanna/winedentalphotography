<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;

/** JPEG uji dengan EXIF buatan (orientasi, tanggal, GPS) untuk menguji pembersihan metadata. */
final class TestImages
{
    public static function jpeg(int $w = 64, int $h = 48, int $orientation = 1, ?string $date = null, bool $gps = false): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefilledrectangle($img, 0, 0, $w, $h, imagecolorallocate($img, 200, 120, 120));
        ob_start();
        imagejpeg($img, null, 90);
        $jpeg = ob_get_clean();

        // Sisipkan APP1 setelah SOI (GD menulis APP0/JFIF; urutan tidak masalah untuk pengurai).
        return substr($jpeg, 0, 2).self::app1($orientation, $date, $gps).substr($jpeg, 2);
    }

    public static function upload(string $bytes, string $name = 'foto.jpg'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, null, null, true);
    }

    /** TIFF big-endian: IFD0 (Orientation, DateTime, GPS pointer) + GPS IFD (GPSLatitudeRef). */
    private static function app1(int $orientation, ?string $date, bool $gps): string
    {
        $entries = [[0x0112, 3, 1, pack('n', $orientation).pack('n', 0)]];
        $extra = '';
        $count = 1 + ($date ? 1 : 0) + ($gps ? 1 : 0);
        $ifdSize = 2 + $count * 12 + 4;
        $offset = 8 + $ifdSize;

        if ($date) {
            $entries[] = [0x0132, 2, 20, pack('N', $offset)];
            $extra .= str_pad($date, 20, "\0");
            $offset += 20;
        }
        if ($gps) {
            $entries[] = [0x8825, 4, 1, pack('N', $offset)];
            $extra .= pack('n', 1).pack('n', 0x0001).pack('n', 2).pack('N', 2)."S\0\0\0".pack('N', 0);
        }

        $tiff = 'MM'.pack('n', 42).pack('N', 8).pack('n', count($entries));
        foreach ($entries as [$tag, $type, $n, $value]) {
            $tiff .= pack('n', $tag).pack('n', $type).pack('N', $n).$value;
        }
        $tiff .= pack('N', 0).$extra;
        $payload = "Exif\0\0".$tiff;

        return "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
    }
}
