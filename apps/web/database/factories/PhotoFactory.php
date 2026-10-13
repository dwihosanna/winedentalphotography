<?php

namespace Database\Factories;

use App\Models\Photo;
use App\Models\PhotoSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Photo> */
class PhotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'photo_session_id' => PhotoSession::factory(),
            'slot_code' => 'IO-01',
            'category' => 'io_left',
            'source' => 'camera',
            'disk' => 'local',
            'original_path' => 'photos/'.fake()->uuid().'.jpg',
            'mime' => 'image/jpeg',
            'original_bytes' => 2_000_000,
            'sha256' => hash('sha256', fake()->uuid()),
            'taken_by' => User::factory(),
            'taken_at' => now(),
        ];
    }
}
