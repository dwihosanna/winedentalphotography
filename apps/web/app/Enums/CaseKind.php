<?php

namespace App\Enums;

/** Jenis kasus: ortodonti (tahap Awal s.d. Retensi) atau non-ortho seperti tambalan/scaling (Sebelum/Sesudah). */
enum CaseKind: string
{
    case Ortho = 'ortho';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Ortho => 'Ortodonti',
            self::General => 'Non-ortho',
        };
    }

    /** @return list<Stage> */
    public function stages(): array
    {
        return match ($this) {
            self::Ortho => [Stage::Initial, Stage::Insertion, Stage::Control, Stage::Debond, Stage::Retention],
            self::General => [Stage::Before, Stage::After],
        };
    }

    /** Tahap yang disarankan untuk sesi baru: tahap pertama, atau tahap lanjutan bila sudah ada sesi (SRS 6.4). */
    public function suggestedStage(bool $hasSessions): Stage
    {
        return match ($this) {
            self::Ortho => $hasSessions ? Stage::Control : Stage::Initial,
            self::General => $hasSessions ? Stage::After : Stage::Before,
        };
    }
}
