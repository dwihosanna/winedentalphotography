<?php

namespace Database\Factories;

use App\Enums\CaseKind;
use App\Models\OrthoCase;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrthoCase> */
class OrthoCaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'kind' => CaseKind::Ortho,
            'brace_type' => 'Konvensional (R)',
            'installed_on' => now()->toDateString(),
            'dentist_user_id' => User::factory(),
        ];
    }

    /** Kasus non-ortho (tambalan, scaling, dll.). */
    public function general(string $treatment = 'Tambalan'): static
    {
        return $this->state(['kind' => CaseKind::General, 'brace_type' => null, 'treatment_type' => $treatment, 'installed_on' => null]);
    }
}
