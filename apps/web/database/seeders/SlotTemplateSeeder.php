<?php

namespace Database\Seeders;

use App\Models\SlotTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

/** Menyamakan tabel slot_templates dengan config/slots.php. Aman dijalankan berulang. */
class SlotTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $slots = config('slots.slots');
        // Migrasi lama menjalankan seeder ini sebelum kolom max_photos ada.
        $hasMax = Schema::hasColumn('slot_templates', 'max_photos');

        foreach (config('slots.stages') as $stage => $rules) {
            $order = 0;
            foreach ($rules as $code => $required) {
                SlotTemplate::updateOrCreate(
                    ['stage' => $stage, 'slot_code' => $code],
                    ($hasMax ? $slots[$code] + ['max_photos' => 1] : Arr::except($slots[$code], 'max_photos'))
                        + ['required' => $required, 'sort_order' => ++$order],
                );
            }

            SlotTemplate::where('stage', $stage)->whereNotIn('slot_code', array_keys($rules))->delete();
        }
    }
}
