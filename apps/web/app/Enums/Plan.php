<?php

namespace App\Enums;

enum Plan: string
{
    case Free = 'free';
    case Premium = 'premium';

    /** Batas akun Free (FR-34); Premium tanpa batas. */
    public function patientLimit(): ?int
    {
        return $this === self::Free ? 50 : null;
    }

    public function storageLimitBytes(): ?int
    {
        return $this === self::Free ? 5 * 1024 ** 3 : null;
    }
}
