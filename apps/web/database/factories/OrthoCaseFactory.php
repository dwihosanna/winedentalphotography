<?php

namespace Database\Factories;

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
            'brace_type' => 'Metal',
            'installed_on' => now()->toDateString(),
            'dentist_user_id' => User::factory(),
        ];
    }
}
