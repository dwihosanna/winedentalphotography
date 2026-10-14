<?php

namespace App\Enums;

/**
 * Tahap sesi foto (FR-05). Ortho: sesi Awal memakai aturan slot sendiri; tahap lain = kunjungan berikutnya (SRS 6.1).
 * Non-ortho: Sebelum/Sesudah, semua slot opsional (lihat CaseKind).
 */
enum Stage: string
{
    case Initial = 'initial';
    case Insertion = 'insertion';
    case Control = 'control';
    case Debond = 'debond';
    case Retention = 'retention';
    case Before = 'before';
    case After = 'after';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Awal',
            self::Insertion => 'Insersi',
            self::Control => 'Kontrol',
            self::Debond => 'Lepas',
            self::Retention => 'Retensi',
            self::Before => 'Sebelum',
            self::After => 'Sesudah',
        };
    }
}
