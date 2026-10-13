<?php

namespace Database\Factories;

use App\Enums\Stage;
use App\Models\OrthoCase;
use App\Models\PhotoSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PhotoSession> */
class PhotoSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ortho_case_id' => OrthoCase::factory(),
            'stage' => Stage::Initial,
            'session_date' => now()->toDateString(),
            'created_by' => User::factory(),
        ];
    }
}
