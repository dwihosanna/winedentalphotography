<?php

namespace App\Enums;

/** Tiga level consent terpisah (SRS Bagian 9). */
enum ConsentType: string
{
    case Clinical = 'clinical';
    case Education = 'education';
    case Publication = 'publication';
}
