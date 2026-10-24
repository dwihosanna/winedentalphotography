<?php

namespace Tests\Feature;

use App\Enums\Stage;
use App\Models\Photo;
use App\Models\PhotoSession;
use App\Models\Report;
use App\Models\SlotTemplate;
use Database\Seeders\SlotTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Aturan dokter 13 Okt 2026 (SRS 6.1), diperbarui 15 dan 18 Okt 2026. */
class SlotRulesTest extends TestCase
{
    use RefreshDatabase;

    private const IO = ['IO-01', 'IO-02', 'IO-03', 'IO-04', 'IO-05', 'IO-06', 'IO-07'];

    private const EO = ['EO-01', 'EO-02', 'EO-03'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SlotTemplateSeeder::class);
    }

    private function required(Stage $stage): array
    {
        return SlotTemplate::where('stage', $stage)->where('required', true)->where('kind', 'photo')->orderBy('sort_order')->pluck('slot_code')->all();
    }

    public function test_initial_session_requires_every_slot_except_free(): void
    {
        $this->assertSame([...self::EO, ...self::IO], $this->required(Stage::Initial));
        $this->assertSame(['RX-01', 'RX-02'], SlotTemplate::where('stage', Stage::Initial)->where('kind', 'xray')->where('required', true)->orderBy('sort_order')->pluck('slot_code')->all());
    }

    public function test_insertion_requires_eo_and_io(): void
    {
        $this->assertSame([...self::EO, ...self::IO], $this->required(Stage::Insertion));
    }

    public function test_debond_and_retention_require_io_only(): void
    {
        foreach ([Stage::Debond, Stage::Retention] as $stage) {
            $this->assertSame(self::IO, $this->required($stage), $stage->value);
            $this->assertSame(3, SlotTemplate::where('stage', $stage)->whereIn('slot_code', self::EO)->where('required', false)->count());
        }
    }

    public function test_control_sessions_require_only_frontal_and_lateral_intraoral(): void
    {
        $this->assertSame(['IO-01', 'IO-02', 'IO-03'], $this->required(Stage::Control));
        $this->assertSame(4, SlotTemplate::where('stage', Stage::Control)->whereIn('slot_code', ['IO-04', 'IO-05', 'IO-06', 'IO-07'])->where('required', false)->count());
        $this->assertSame('Intraoral kanan', SlotTemplate::where('slot_code', 'IO-01')->value('label'));
        $this->assertSame('Intraoral kiri', SlotTemplate::where('slot_code', 'IO-03')->value('label'));
    }

    public function test_xray_is_optional_except_at_initial(): void
    {
        $this->assertSame(['initial'], SlotTemplate::where('kind', 'xray')->where('required', true)->distinct()->pluck('stage')->map->value->all());
        $this->assertSame(2 * count(Stage::cases()), SlotTemplate::where('kind', 'xray')->count());
    }

    public function test_free_slot_is_always_optional_and_holds_three_photos(): void
    {
        $this->assertSame(0, SlotTemplate::where('slot_code', 'FREE')->where('required', true)->count());
        $this->assertSame(count(Stage::cases()), SlotTemplate::where('slot_code', 'FREE')->where('max_photos', 3)->count());
        $this->assertSame(0, SlotTemplate::where('slot_code', '!=', 'FREE')->where('max_photos', '!=', 1)->count());
    }

    public function test_merge_migration_moves_split_free_photos_back_to_free(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $this->inWorkspace($workspace);
        $session = PhotoSession::factory()->create(['stage' => Stage::Initial]);
        $first = Photo::factory()->create(['photo_session_id' => $session->id, 'slot_code' => 'FREE-1']);
        $second = Photo::factory()->create(['photo_session_id' => $session->id, 'slot_code' => 'FREE-2']);
        $report = Report::create([
            'ortho_case_id' => $session->ortho_case_id, 'template' => 'before_after', 'before_session_id' => $session->id,
            'after_session_id' => $session->id, 'selected_photos' => ['FREE-1' => ['before' => $first->id, 'after' => null], 'FREE-2' => ['before' => $second->id, 'after' => null]],
            'created_by' => $dentist->id,
        ]);
        Schema::table('slot_templates', fn ($table) => $table->dropColumn('max_photos'));

        (require database_path('migrations/2026_10_24_052152_merge_free_slots_into_one_with_three_photos.php'))->up();

        $this->assertSame(['FREE', 'FREE'], [$first->fresh()->slot_code, $second->fresh()->slot_code]);
        $this->assertSame(['FREE'], array_keys($report->fresh()->selected_photos));
        $this->assertSame($first->id, $report->fresh()->selected_photos['FREE']['before']);
    }

    public function test_session_is_complete_only_when_required_slots_are_filled_and_retakes_do_not_count_twice(): void
    {
        [, $workspace] = $this->dentistWithWorkspace();
        $this->inWorkspace($workspace);
        $session = PhotoSession::factory()->create(['stage' => Stage::Control, 'control_number' => 1]);

        foreach (['IO-01', 'IO-02', 'IO-03'] as $code) {
            Photo::factory()->create(['photo_session_id' => $session->id, 'slot_code' => $code]);
        }
        $session->refreshStatus();
        $this->assertSame('complete', $session->status); // IO-04 s.d. IO-07 opsional saat kontrol

        // Retake: foto lama ditandai diganti, foto baru mengisi slot yang sama.
        $old = $session->currentPhotos()->where('slot_code', 'IO-01')->first();
        $new = Photo::factory()->create(['photo_session_id' => $session->id, 'slot_code' => 'IO-01']);
        $old->forceFill(['replaced_by_photo_id' => $new->id])->save();
        $session->refreshStatus();
        $this->assertSame('complete', $session->status);
        $this->assertSame(2, $session->photos()->where('slot_code', 'IO-01')->count());
    }

    public function test_initial_session_without_eo_stays_draft(): void
    {
        [, $workspace] = $this->dentistWithWorkspace();
        $this->inWorkspace($workspace);
        $session = PhotoSession::factory()->create(['stage' => Stage::Initial]);

        foreach (self::IO as $code) {
            Photo::factory()->create(['photo_session_id' => $session->id, 'slot_code' => $code]);
        }
        $session->refreshStatus();

        $this->assertSame('draft', $session->status);
        $this->assertSame([...self::EO, 'RX-01', 'RX-02'], $session->missingRequiredSlots());
    }

    public function test_non_ortho_stages_have_all_slots_optional(): void
    {
        foreach ([Stage::Before, Stage::After] as $stage) {
            $this->assertSame(0, SlotTemplate::where('stage', $stage)->where('required', true)->count(), $stage->value);
            $this->assertSame(13, SlotTemplate::where('stage', $stage)->count(), $stage->value);
        }
    }

    public function test_non_ortho_session_is_complete_once_it_has_one_photo(): void
    {
        [, $workspace] = $this->dentistWithWorkspace();
        $this->inWorkspace($workspace);
        $session = PhotoSession::factory()->create(['stage' => Stage::Before]);

        $session->refreshStatus();
        $this->assertSame('draft', $session->status);

        Photo::factory()->create(['photo_session_id' => $session->id, 'slot_code' => 'FREE']);
        $session->refreshStatus();
        $this->assertSame('complete', $session->status);
    }

    public function test_swap_migration_moves_old_lateral_photos_by_content(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $this->inWorkspace($workspace);
        $session = PhotoSession::factory()->create(['stage' => Stage::Initial]);
        // Data lama: IO-01 dulu = kiri (io_left), IO-03 dulu = kanan (io_right).
        $left = Photo::factory()->create(['photo_session_id' => $session->id, 'slot_code' => 'IO-01', 'category' => 'io_left']);
        $right = Photo::factory()->create(['photo_session_id' => $session->id, 'slot_code' => 'IO-03', 'category' => 'io_right']);
        $report = Report::create([
            'ortho_case_id' => $session->ortho_case_id, 'template' => 'before_after', 'before_session_id' => $session->id,
            'after_session_id' => $session->id, 'selected_photos' => ['IO-01' => ['before' => $left->id, 'after' => null], 'IO-02' => ['before' => null, 'after' => null]],
            'created_by' => $dentist->id,
        ]);

        (require database_path('migrations/2026_10_15_032606_swap_io01_io03_and_relax_control_slots.php'))->up();

        $this->assertSame(['IO-03', 'io_left'], [$left->fresh()->slot_code, $left->fresh()->category]);
        $this->assertSame(['IO-01', 'io_right'], [$right->fresh()->slot_code, $right->fresh()->category]);
        // Urutan kunci JSON diatur MySQL; yang penting isinya ditukar.
        $this->assertEqualsCanonicalizing(['IO-02', 'IO-03'], array_keys($report->fresh()->selected_photos));
        $this->assertSame($left->id, $report->fresh()->selected_photos['IO-03']['before']);
    }

    public function test_control_number_increments_per_case(): void
    {
        [, $workspace] = $this->dentistWithWorkspace();
        $this->inWorkspace($workspace);
        $session = PhotoSession::factory()->create(['stage' => Stage::Control, 'control_number' => 1]);
        $case = $session->orthoCase;

        $this->assertSame(2, $case->nextControlNumber());
    }
}
