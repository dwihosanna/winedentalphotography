<?php

namespace Tests\Feature;

use App\Enums\Stage;
use App\Models\Photo;
use App\Models\PhotoSession;
use App\Models\SlotTemplate;
use Database\Seeders\SlotTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Aturan dokter 13 Okt 2026 (SRS 6.1). */
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

    public function test_initial_session_requires_eo_and_io(): void
    {
        $this->assertSame([...self::EO, ...self::IO], $this->required(Stage::Initial));
    }

    public function test_follow_up_sessions_require_io_only(): void
    {
        foreach ([Stage::Insertion, Stage::Control, Stage::Debond, Stage::Retention] as $stage) {
            $this->assertSame(self::IO, $this->required($stage), $stage->value);
            $this->assertSame(3, SlotTemplate::where('stage', $stage)->whereIn('slot_code', self::EO)->where('required', false)->count());
        }
    }

    public function test_xray_is_optional_in_every_stage(): void
    {
        $this->assertSame(0, SlotTemplate::where('kind', 'xray')->where('required', true)->count());
        $this->assertSame(2 * count(Stage::cases()), SlotTemplate::where('kind', 'xray')->count());
    }

    public function test_free_slot_is_always_optional(): void
    {
        $this->assertSame(0, SlotTemplate::where('slot_code', 'FREE')->where('required', true)->count());
        $this->assertSame(count(Stage::cases()), SlotTemplate::where('slot_code', 'FREE')->count());
    }

    public function test_session_is_complete_only_when_required_slots_are_filled_and_retakes_do_not_count_twice(): void
    {
        [, $workspace] = $this->dentistWithWorkspace();
        $this->inWorkspace($workspace);
        $session = PhotoSession::factory()->create(['stage' => Stage::Control, 'control_number' => 1]);

        foreach (self::IO as $code) {
            Photo::factory()->create(['photo_session_id' => $session->id, 'slot_code' => $code]);
        }
        $session->refreshStatus();
        $this->assertSame('complete', $session->status);

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
        $this->assertSame(self::EO, $session->missingRequiredSlots());
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

    public function test_control_number_increments_per_case(): void
    {
        [, $workspace] = $this->dentistWithWorkspace();
        $this->inWorkspace($workspace);
        $session = PhotoSession::factory()->create(['stage' => Stage::Control, 'control_number' => 1]);
        $case = $session->orthoCase;

        $this->assertSame(2, $case->nextControlNumber());
    }
}
