<?php

namespace Tests\Feature;

use App\Enums\Stage;
use App\Models\OrthoCase;
use App\Models\Patient;
use App\Models\Photo;
use App\Models\PhotoSession;
use App\Models\User;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** FR-15 (galeri per kategori + filter), FR-17 (perbandingan), FR-18 (metadata foto). */
class GalleryTest extends TestCase
{
    use RefreshDatabase;

    private function in(Workspace $workspace, callable $fn): mixed
    {
        $result = app(CurrentWorkspace::class)->run($workspace, $fn);
        app(CurrentWorkspace::class)->set(null);

        return $result;
    }

    /**
     * Pasien dengan tiga sesi: Awal (Jan), Kontrol ke-1 (Mar), Lepas (Jun); IO-01 di setiap sesi,
     * EO-01 hanya di sesi Awal, dan satu IO-01 lama di sesi Awal yang sudah diganti retake.
     *
     * @return array{0: Patient, 1: array<string, PhotoSession>, 2: array<string, Photo>}
     */
    private function patientWithPhotos(Workspace $workspace, User $dentist): array
    {
        return $this->in($workspace, function () use ($dentist) {
            $patient = Patient::factory()->create();
            $case = OrthoCase::factory()->create(['patient_id' => $patient->id, 'dentist_user_id' => $dentist->id]);
            $session = fn (Stage $stage, string $date, ?int $n = null) => PhotoSession::factory()->create([
                'ortho_case_id' => $case->id, 'stage' => $stage, 'session_date' => $date, 'control_number' => $n, 'created_by' => $dentist->id,
            ]);
            $sessions = [
                'initial' => $session(Stage::Initial, '2026-01-10'),
                'control' => $session(Stage::Control, '2026-03-10', 1),
                'debond' => $session(Stage::Debond, '2026-06-10'),
            ];
            $photo = fn (PhotoSession $s, string $slot, array $attrs = []) => Photo::factory()->create([
                'photo_session_id' => $s->id, 'slot_code' => $slot, 'category' => config("slots.slots.$slot.category"),
                'taken_by' => $dentist->id, 'device' => 'iPhone 15', 'lens_mode' => 'main', 'zoom' => 2, 'overlay_version' => '2.2.0',
            ] + $attrs);

            $photos = [
                'initial' => $photo($sessions['initial'], 'IO-01'),
                'control' => $photo($sessions['control'], 'IO-01'),
                'debond' => $photo($sessions['debond'], 'IO-01'),
                'face' => $photo($sessions['initial'], 'EO-01'),
            ];
            $photo($sessions['initial'], 'IO-01', ['replaced_by_photo_id' => $photos['initial']->id]);

            return [$patient, $sessions, $photos];
        });
    }

    public function test_gallery_groups_current_photos_per_category_in_date_order(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        [$patient] = $this->patientWithPhotos($workspace, $dentist);

        $this->actingAs($dentist)->get("/pasien/{$patient->id}/galeri")
            ->assertOk()
            ->assertSeeInOrder(['EO-01 · Wajah depan, rest', 'IO-01 · Intraoral kanan', '(3)', '10-01-26 · Awal', '10-03-26 · Kontrol ke-1', '10-06-26 · Lepas'])
            ->assertSee(route('gallery.compare', ['patient' => $patient, 'slot' => 'IO-01']), false)
            ->assertDontSee(route('gallery.compare', ['patient' => $patient, 'slot' => 'EO-01']), false);
    }

    public function test_gallery_filters_by_category_stage_and_date_range(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        [$patient] = $this->patientWithPhotos($workspace, $dentist);
        $url = "/pasien/{$patient->id}/galeri";

        // Isi galeri per kategori: [slot => jumlah foto]; dropdown filter juga memuat label slot, jadi dicek lewat data view.
        $counts = fn (string $query) => $this->actingAs($dentist)->get("$url?$query")->assertOk()
            ->viewData('groups')->map->count()->all();

        $this->assertSame(['EO-01' => 1], $counts('slot=EO-01'));
        $this->assertSame(['IO-01' => 1], $counts('stage=debond'));
        $this->assertSame(['IO-01' => 1], $counts('from=2026-02-01&to=2026-04-01'));
        $this->actingAs($dentist)->get("$url?stage=retention")->assertOk()->assertSee('Tidak ada foto yang cocok');
        $this->actingAs($dentist)->get("$url?slot=XX-99")->assertSessionHasErrors('slot');
    }

    public function test_compare_defaults_to_first_and_last_session_and_accepts_choice(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        [$patient, $sessions, $photos] = $this->patientWithPhotos($workspace, $dentist);
        $url = "/pasien/{$patient->id}/banding?slot=IO-01";

        $this->actingAs($dentist)->get($url)->assertOk()
            ->assertViewHas('a', fn (Photo $a) => $a->is($photos['initial']))
            ->assertViewHas('b', fn (Photo $b) => $b->is($photos['debond']));

        $this->actingAs($dentist)->get("$url&a={$sessions['control']->id}&b={$sessions['debond']->id}")->assertOk()
            ->assertViewHas('a', fn (Photo $a) => $a->is($photos['control']));

        // Dari detail foto sesi paling awal: b = sesi itu, a tidak boleh sama.
        $this->actingAs($dentist)->get("$url&b={$sessions['initial']->id}")->assertOk()
            ->assertViewHas('a', fn (Photo $a) => ! $a->is($photos['initial']));

        $this->actingAs($dentist)->get("/pasien/{$patient->id}/banding?slot=EO-01")->assertOk()
            ->assertViewHas('b', null)->assertSee('minimal dua sesi');
    }

    public function test_photo_detail_shows_automatic_metadata(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        [$patient, , $photos] = $this->patientWithPhotos($workspace, $dentist);

        $this->actingAs($dentist)->get("/foto/{$photos['initial']->id}")->assertOk()
            ->assertSee($patient->dpid)
            ->assertSee('Awal · 10 Januari 2026')
            ->assertSee('IO-01 · Intraoral kanan')
            ->assertSee('Kamera aplikasi')
            ->assertSee($dentist->name)
            ->assertSee('main · 2×')
            ->assertSee('2.2.0')
            ->assertSee('1 foto sebelumnya diganti');
    }

    public function test_photo_opens_in_app_viewer_with_back_and_share(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        [$patient, , $photos] = $this->patientWithPhotos($workspace, $dentist);
        $photo = $photos['initial'];
        $fileName = "{$patient->name}-IO-01-10-01-2026.jpg";

        // Detail foto: ketuk foto = penampil dalam aplikasi (bukan tab baru), tombol Bagikan di bar atas.
        $this->actingAs($dentist)->get("/foto/{$photo->id}")->assertOk()
            ->assertSee(route('photos.image', $photo), false)
            ->assertSee('data-share-file', false)
            ->assertSee('data-name="'.e($fileName).'"', false)
            ->assertSee('data-stamp="'.e("{$patient->firstName()} - Awal - 10-01-2026").'"', false)
            ->assertDontSee('target="_blank"', false);

        $this->actingAs($dentist)->get("/foto/{$photo->id}/tampil")->assertOk()
            ->assertSee('aria-label="Kembali"', false)
            ->assertSee(route('photos.show', $photo), false)
            ->assertSee('Bagikan')
            ->assertViewHas('fileName', $fileName)
            ->assertSee('data-stamp="'.e("{$patient->firstName()} - Awal - 10-01-2026").'"', false)
            ->assertViewHas('variant', 'original');
    }

    public function test_export_watermark_is_patient_session_and_date(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        [$patient, , $photos] = $this->patientWithPhotos($workspace, $dentist);

        $patient->name = 'Ayu Kusuma Setiawati';
        $this->assertSame('Ayu', $patient->firstName());
        $texts = $this->in($workspace, fn () => array_map(fn ($key) => $photos[$key]->watermarkText($patient), ['initial', 'control', 'debond']));
        $this->assertSame(['Ayu - Awal - 10-01-2026', 'Ayu - Kontrol ke-1 - 10-03-2026', 'Ayu - Lepas - 10-06-2026'], $texts);
    }

    public function test_gallery_pages_are_limited_to_own_workspace(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        [$other, $otherWorkspace] = $this->dentistWithWorkspace();
        [$patient, , $photos] = $this->patientWithPhotos($otherWorkspace, $other);

        $this->actingAs($dentist)->get("/pasien/{$patient->id}/galeri")->assertNotFound();
        $this->actingAs($dentist)->get("/pasien/{$patient->id}/banding?slot=IO-01")->assertNotFound();
        $this->actingAs($dentist)->get("/foto/{$photos['initial']->id}")->assertNotFound();
        $this->actingAs($dentist)->get("/foto/{$photos['initial']->id}/tampil")->assertNotFound();
    }
}
