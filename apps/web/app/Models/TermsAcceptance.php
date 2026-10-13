<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

#[WithoutTimestamps]
#[Fillable(['user_id', 'terms_version', 'dentist_declaration', 'accepted_at', 'ip_address', 'user_agent'])]
class TermsAcceptance extends Model
{
    protected function casts(): array
    {
        return ['dentist_declaration' => 'boolean', 'accepted_at' => 'datetime'];
    }
}
