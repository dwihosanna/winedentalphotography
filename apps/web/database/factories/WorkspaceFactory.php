<?php

namespace Database\Factories;

use App\Enums\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Workspace> */
class WorkspaceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'practice_name' => 'Praktik '.fake()->lastName(),
            'owner_user_id' => User::factory(),
            'plan' => Plan::Free,
            'patient_limit' => Plan::Free->patientLimit(),
            'storage_limit_bytes' => Plan::Free->storageLimitBytes(),
        ];
    }

    public function premium(): static
    {
        return $this->state(['plan' => Plan::Premium, 'patient_limit' => null, 'storage_limit_bytes' => null]);
    }
}
