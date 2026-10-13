<?php

namespace Database\Seeders;

use App\Models\SlotTemplate;
use Illuminate\Database\Seeder;

/** Menyamakan tabel slot_templates dengan config/slots.php. Aman dijalankan berulang. */
class SlotTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $slots = config('slots.slots');

        foreach (config('slots.stages') as $stage => $rules) {
            $order = 0;
            foreach ($rules as $code => $required) {
                SlotTemplate::updateOrCreate(
                    ['stage' => $stage, 'slot_code' => $code],
                    $slots[$code] + ['required' => $required, 'sort_order' => ++$order],
                );
            }

            SlotTemplate::where('stage', $stage)->whereNotIn('slot_code', array_keys($rules))->delete();
        }
    }
}
