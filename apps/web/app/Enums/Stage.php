<?php

namespace App\Enums;

/** Tahap sesi foto (FR-05). Sesi Awal memakai aturan slot sendiri; tahap lain = kunjungan berikutnya (SRS 6.1). */
enum Stage: string
{
    case Initial = 'initial';
    case Insertion = 'insertion';
    case Control = 'control';
    case Debond = 'debond';
    case Retention = 'retention';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Awal',
            self::Insertion => 'Insersi',
            self::Control => 'Kontrol',
            self::Debond => 'Lepas',
            self::Retention => 'Retensi',
        };
    }
}
