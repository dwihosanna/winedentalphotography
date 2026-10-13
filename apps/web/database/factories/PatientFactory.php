<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Patient> Butuh workspace aktif (CurrentWorkspace), sama seperti di aplikasi. */
class PatientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'birth_date' => fake()->dateTimeBetween('-40 years', '-10 years')->format('Y-m-d'),
            'phone' => fake()->numerify('08##########'),
        ];
    }
}
