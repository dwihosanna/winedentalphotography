<?php

namespace Tests\Feature;

use App\Enums\CaseKind;
use App\Enums\Stage;
use App\Models\AuditLog;
use App\Models\OrthoCase;
use App\Models\Patient;
use App\Models\Photo;
use App\Models\PhotoSession;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use App\Support\SlotGuides;
use Database\Seeders\SlotTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestImages;
use Tests\TestCase;

/** FR-04, FR-05, FR-06/07/08 (endpoint unggah), FR-09, FR-10, FR-33, akses file. */
class SessionPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SlotTemplateSeeder::class);
        Storage::fake('photos');
    }

    private function in(Workspace $workspace, callable $fn): mixed
    {
        $result = app(CurrentWorkspace::class)->run($workspace, $fn);
        app(CurrentWorkspace::class)->set(null);

        return $result;
    }

    private function sessionIn(Workspace $workspace, Stage $stage = Stage::Initial, array $attrs = []): PhotoSession
    {
        return $this->in($workspace, fn () => PhotoSession::factory()->create(['stage' => $stage] + $attrs));
    }

    private function jpeg(array $opts = []): UploadedFile
    {
        return TestImages::upload(TestImages::jpeg(...$opts));
    }

    public function test_dentist_creates_case_and_initial_session_with_past_date(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $patient = $this->in($workspace, fn () => Patient::factory()->create());

        $this->actingAs($dentist)->get("/pasien/{$patient->id}/sesi/baru")->assertOk()->assertSee('Kasus baru');
        $this->actingAs($dentist)->post("/pasien/{$patient->id}/sesi", [
            'ortho_case_id' => 'new', 'case_kind' => 'ortho', 'brace_type' => 'SL Metal', 'stage' => 'initial', 'session_date' => '2023-03-01',
        ])->assertRedirect();

        $session = PhotoSession::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(Stage::Initial, $session->stage);
        $this->assertSame('2023-03-01', $session->session_date->toDateString());
        $this->assertSame($workspace->id, $session->workspace_id);
    }

    public function test_control_sessions_are_numbered_per_case(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $case = $this->in($workspace, fn () => OrthoCase::factory()->create());

        foreach ([1, 2] as $n) {
            $this->actingAs($dentist)->post("/pasien/{$case->patient_id}/sesi", [
                'ortho_case_id' => $case->id, 'stage' => 'control', 'session_date' => now()->toDateString(),
            ])->assertRedirect();
        }

        $this->assertSame([1, 2], PhotoSession::withoutGlobalScopes()->orderBy('control_number')->pluck('control_number')->all());
        $this->actingAs($dentist)->get("/pasien/{$case->patient_id}/arsip")->assertOk()->assertSee('Kontrol ke-2');
    }

    public function test_assistant_can_create_session_but_not_case(): void
    {
        [, $workspace] = $this->dentistWithWorkspace(premium: true);
        $assistant = $this->assistantIn($workspace);
        $case = $this->in($workspace, fn () => OrthoCase::factory()->create());

        $this->actingAs($assistant)->post("/pasien/{$case->patient_id}/sesi", [
            'ortho_case_id' => 'new', 'case_kind' => 'ortho', 'brace_type' => 'SL Metal', 'stage' => 'initial', 'session_date' => now()->toDateString(),
        ])->assertForbidden();
        $this->actingAs($assistant)->post("/pasien/{$case->patient_id}/sesi", [
            'ortho_case_id' => $case->id, 'stage' => 'control', 'session_date' => now()->toDateString(),
        ])->assertRedirect();
    }

    public function test_case_of_another_patient_is_rejected(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $other = $this->in($workspace, fn () => OrthoCase::factory()->create());
        $patient = $this->in($workspace, fn () => Patient::factory()->create());

        $this->actingAs($dentist)->post("/pasien/{$patient->id}/sesi", [
            'ortho_case_id' => $other->id, 'stage' => 'control', 'session_date' => now()->toDateString(),
        ])->assertSessionHasErrors('ortho_case_id');
    }

    public function test_dentist_creates_non_ortho_case_with_before_stage(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $patient = $this->in($workspace, fn () => Patient::factory()->create());

        $this->actingAs($dentist)->get("/pasien/{$patient->id}/sesi/baru")->assertOk()->assertSee('Non-ortho')->assertSee('Konvensional (R)')->assertSee('Scaling');
        $this->actingAs($dentist)->post("/pasien/{$patient->id}/sesi", [
            'ortho_case_id' => 'new', 'case_kind' => 'general', 'treatment_type' => 'other', 'treatment_type_other' => 'Perawatan saluran akar',
            'brace_type' => 'SL Metal', 'installed_on' => '2025-01-01', // isian ortho diabaikan untuk kasus non-ortho
            'stage' => 'before', 'session_date' => now()->toDateString(),
        ])->assertRedirect();

        $case = OrthoCase::withoutGlobalScopes()->sole();
        $this->assertSame(CaseKind::General, $case->kind);
        $this->assertSame('Perawatan saluran akar', $case->treatment_type);
        $this->assertNull($case->brace_type);
        $this->assertNull($case->installed_on);
        $this->assertSame(Stage::Before, PhotoSession::withoutGlobalScopes()->sole()->stage);
    }

    public function test_stage_must_match_case_kind_and_types_come_from_list(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $patient = $this->in($workspace, fn () => Patient::factory()->create());
        $general = $this->in($workspace, fn () => OrthoCase::factory()->general()->create(['patient_id' => $patient->id]));
        $url = "/pasien/{$patient->id}/sesi";
        $today = now()->toDateString();

        $this->actingAs($dentist)->post($url, ['ortho_case_id' => $general->id, 'stage' => 'control', 'session_date' => $today])
            ->assertSessionHasErrors('stage');
        $this->actingAs($dentist)->post($url, ['ortho_case_id' => 'new', 'case_kind' => 'general', 'treatment_type' => 'Scaling', 'stage' => 'initial', 'session_date' => $today])
            ->assertSessionHasErrors('stage');
        $this->actingAs($dentist)->post($url, ['ortho_case_id' => 'new', 'case_kind' => 'ortho', 'brace_type' => 'Metal', 'stage' => 'initial', 'session_date' => $today])
            ->assertSessionHasErrors('brace_type');
        $this->actingAs($dentist)->post($url, ['ortho_case_id' => 'new', 'case_kind' => 'ortho', 'brace_type' => 'other', 'stage' => 'initial', 'session_date' => $today])
            ->assertSessionHasErrors('brace_type_other');

        $this->actingAs($dentist)->post($url, ['ortho_case_id' => 'new', 'case_kind' => 'ortho', 'brace_type' => 'other', 'brace_type_other' => 'Clear aligner', 'stage' => 'initial', 'session_date' => $today])
            ->assertRedirect();
        $this->assertSame('Clear aligner', OrthoCase::withoutGlobalScopes()->where('kind', 'ortho')->value('brace_type'));
        $this->actingAs($dentist)->post($url, ['ortho_case_id' => $general->id, 'stage' => 'after', 'session_date' => $today])->assertRedirect();
    }

    public function test_control_number_follows_visit_date_when_old_controls_are_uploaded_later(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $patient = $this->in($workspace, fn () => Patient::factory()->create());
        $case = $this->in($workspace, fn () => OrthoCase::factory()->create(['patient_id' => $patient->id]));
        $url = "/pasien/{$patient->id}/sesi";
        $control = fn (string $date) => $this->actingAs($dentist)->post($url, ['ortho_case_id' => $case->id, 'stage' => 'control', 'session_date' => $date])->assertRedirect();

        // Kunjungan hari ini dicatat lebih dulu → sementara Kontrol ke-1.
        $control('2026-10-15');
        $number = fn () => PhotoSession::withoutGlobalScopes()->where('stage', 'control')->orderBy('session_date')->pluck('control_number', 'session_date')
            ->mapWithKeys(fn ($n, $date) => [substr($date, 0, 10) => $n])->all();
        $this->assertSame(['2026-10-15' => 1], $number());

        // Lalu foto kontrol 1–5 yang lama diunggah (tanggal lebih awal, urutan input acak).
        foreach (['2026-03-01', '2026-01-05', '2026-07-01', '2026-02-02', '2026-05-10'] as $date) {
            $control($date);
        }
        $this->assertSame([
            '2026-01-05' => 1, '2026-02-02' => 2, '2026-03-01' => 3, '2026-05-10' => 4, '2026-07-01' => 5, '2026-10-15' => 6,
        ], $number());

        // Tanggal sesi dikoreksi → nomor ikut bergeser.
        $july = PhotoSession::withoutGlobalScopes()->whereDate('session_date', '2026-07-01')->sole();
        $this->actingAs($dentist)->put("/sesi/{$july->id}", ['session_date' => '2025-12-20'])->assertRedirect();
        $this->assertSame([
            '2025-12-20' => 1, '2026-01-05' => 2, '2026-02-02' => 3, '2026-03-01' => 4, '2026-05-10' => 5, '2026-10-15' => 6,
        ], $number());
        $this->actingAs($dentist)->get("/sesi/{$july->id}")->assertSee('Kontrol ke-1');
    }

    public function test_camera_upload_stores_clean_jpeg_thumb_hash_and_updates_quota(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $session = $this->sessionIn($workspace);
        $file = $this->jpeg(['w' => 120, 'h' => 80, 'orientation' => 6, 'gps' => true]);

        $res = $this->actingAs($dentist)->postJson("/sesi/{$session->id}/foto", [
            'file' => $file, 'slot_code' => 'IO-01', 'source' => 'camera', 'lens_mode' => 'main', 'zoom' => 2,
        ])->assertCreated()->assertJsonPath('slot_code', 'IO-01')->assertJsonPath('session_status', 'draft');

        $photo = Photo::withoutGlobalScopes()->findOrFail($res->json('id'));
        $stored = Storage::disk('photos')->get($photo->original_path);
        $this->assertSame(hash('sha256', $stored), $photo->sha256);
        $this->assertStringNotContainsString('GPS', $stored);
        $this->assertSame([80, 120], [$photo->width, $photo->height]); // orientasi 6: tampil tegak
        $this->assertSame('main', $photo->lens_mode);
        $this->assertSame(SlotGuides::version(), $photo->overlay_version);
        Storage::disk('photos')->assertExists($photo->thumb_path);
        $this->assertSame(strlen($stored), $workspace->fresh()->used_bytes);
        $this->assertTrue(AuditLog::where('action', 'upload')->where('entity_id', $photo->id)->exists());
    }

    public function test_retake_keeps_old_photo_and_completes_session(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $session = $this->sessionIn($workspace, Stage::Control, ['control_number' => 1]);

        foreach (['IO-01', 'IO-02', 'IO-03', 'IO-04', 'IO-05', 'IO-06', 'IO-07', 'IO-01'] as $code) {
            $last = $this->actingAs($dentist)->postJson("/sesi/{$session->id}/foto", [
                'file' => $this->jpeg(), 'slot_code' => $code, 'source' => 'camera',
            ])->assertCreated();
        }

        $last->assertJsonPath('session_status', 'complete')->assertJsonPath('missing', []);
        $io1 = Photo::withoutGlobalScopes()->where('slot_code', 'IO-01')->get();
        $this->assertCount(2, $io1);
        // created_at bisa sama detiknya, jadi foto lama dicari lewat id, bukan urutan waktu.
        $old = $io1->firstWhere('id', '!=', $last->json('id'));
        $this->assertSame($last->json('id'), $old->replaced_by_photo_id);
        $this->assertNull($io1->firstWhere('id', $last->json('id'))->replaced_by_photo_id);
        $this->assertTrue(AuditLog::where('action', 'replace')->exists());
    }

    public function test_upload_rules_per_slot_kind(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $session = $this->sessionIn($workspace);
        $pdf = UploadedFile::fake()->createWithContent('rontgen.pdf', "%PDF-1.4\n%test\n");

        $this->actingAs($dentist)->postJson("/sesi/{$session->id}/foto", ['file' => $pdf, 'slot_code' => 'IO-01', 'source' => 'upload'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $rx = $this->actingAs($dentist)->postJson("/sesi/{$session->id}/foto", ['file' => $pdf, 'slot_code' => 'RX-01', 'source' => 'upload'])
            ->assertCreated()->json('id');
        // Rontgen PDF dibuka di penampil dalam aplikasi; foto biasa tidak punya halaman penampil PDF.
        $this->actingAs($dentist)->get("/foto/{$rx}/lihat")->assertOk()->assertSee('pdf-config', false)->assertSee('Rontgen panoramik');
        $this->actingAs($dentist)->get("/sesi/{$session->id}")->assertSee(route('photos.viewer', $rx), false);
        $this->actingAs($dentist)->postJson("/sesi/{$session->id}/foto", ['file' => $this->jpeg(), 'slot_code' => 'XX-99', 'source' => 'upload'])
            ->assertUnprocessable()->assertJsonValidationErrors('slot_code');
    }

    public function test_old_photo_upload_keeps_exif_date_and_warns_when_far_from_session_date(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $session = $this->sessionIn($workspace, Stage::Initial, ['session_date' => '2024-05-01']);

        $this->actingAs($dentist)->postJson("/sesi/{$session->id}/foto", [
            'file' => $this->jpeg(['date' => '2024:05:02 09:00:00']), 'slot_code' => 'EO-01', 'source' => 'upload',
        ])->assertCreated()->assertJsonPath('exif_date_mismatch', false);
        $this->actingAs($dentist)->postJson("/sesi/{$session->id}/foto", [
            'file' => $this->jpeg(['date' => '2022:01:01 09:00:00']), 'slot_code' => 'EO-02', 'source' => 'upload',
        ])->assertCreated()->assertJsonPath('exif_date_mismatch', true);

        $photo = Photo::withoutGlobalScopes()->where('slot_code', 'EO-01')->first();
        $this->assertSame('2024-05-02 09:00:00', $photo->exif_taken_at->toDateTimeString());
        $this->assertSame('foto.jpg', $photo->original_name);
    }

    public function test_first_eo02_photo_becomes_patient_avatar(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $patient = $this->in($workspace, fn () => Patient::factory()->create());
        $case = $this->in($workspace, fn () => OrthoCase::factory()->create(['patient_id' => $patient->id]));
        $later = $this->in($workspace, fn () => PhotoSession::factory()->create(['ortho_case_id' => $case->id, 'stage' => Stage::Debond, 'session_date' => '2026-09-01']));
        $first = $this->in($workspace, fn () => PhotoSession::factory()->create(['ortho_case_id' => $case->id, 'stage' => Stage::Initial, 'session_date' => '2025-01-10']));
        $upload = fn (PhotoSession $s, string $slot) => $this->actingAs($dentist)->postJson("/sesi/{$s->id}/foto", [
            'file' => $this->jpeg(), 'slot_code' => $slot, 'source' => 'camera',
        ])->assertCreated()->json('id');
        $avatar = fn () => Patient::withoutGlobalScopes()->find($patient->id)->avatar_photo_id;

        // Tanpa EO-02: tetap inisial. EO-01 tidak lagi dipakai.
        $upload($first, 'EO-01');
        $this->assertNull($avatar());
        $this->actingAs($dentist)->get("/pasien/{$patient->id}")->assertSee('<span class="avatar avatar-lg">'.$patient->initials().'</span>', false);

        // EO-02 sesi lebih baru diunggah dulu, lalu EO-02 sesi Awal: yang dipakai EO-02 pertama (sesi Awal).
        $laterEo2 = $upload($later, 'EO-02');
        $this->assertSame($laterEo2, $avatar());
        $firstEo2 = $upload($first, 'EO-02');
        $this->assertSame($firstEo2, $avatar());

        // Retake EO-02 sesi Awal: foto pengganti menjadi foto profil.
        $retake = $upload($first, 'EO-02');
        $this->assertSame($retake, $avatar());

        // Tanggal sesi Lepas dikoreksi lebih awal dari sesi Awal: EO-02 sesi itu menjadi yang pertama.
        $this->actingAs($dentist)->put("/sesi/{$later->id}", ['session_date' => '2024-12-01'])->assertRedirect();
        $this->assertSame($laterEo2, $avatar());

        $this->actingAs($dentist)->get("/pasien/{$patient->id}")->assertSee('avatar avatar-lg avatar-photo', false);
        $this->actingAs($dentist)->get('/pasien')->assertSee('avatar avatar-photo', false);
    }

    public function test_full_storage_rejects_upload_and_keeps_no_file(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $workspace->addUsedBytes($workspace->storage_limit_bytes - 10);
        $session = $this->sessionIn($workspace);

        $this->actingAs($dentist)->postJson("/sesi/{$session->id}/foto", ['file' => $this->jpeg(), 'slot_code' => 'IO-01', 'source' => 'camera'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->assertSame(0, Photo::withoutGlobalScopes()->count());
        $this->assertSame([], Storage::disk('photos')->allFiles());
    }

    public function test_form_upload_without_javascript_redirects_back(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $session = $this->sessionIn($workspace);

        $this->actingAs($dentist)->post("/sesi/{$session->id}/foto", ['file' => $this->jpeg(), 'slot_code' => 'IO-02', 'source' => 'upload'])
            ->assertRedirect("/sesi/{$session->id}");
    }

    public function test_photo_files_need_signature_and_same_workspace(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        [$other] = $this->dentistWithWorkspace();
        $session = $this->sessionIn($workspace);
        $id = $this->actingAs($dentist)->postJson("/sesi/{$session->id}/foto", ['file' => $this->jpeg(), 'slot_code' => 'IO-01', 'source' => 'camera'])->json('id');
        $photo = Photo::withoutGlobalScopes()->find($id);

        $this->actingAs($dentist)->get($photo->url('thumb'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($dentist)->get($photo->url('original'))->assertOk();
        $this->assertTrue(AuditLog::where('action', 'view')->where('entity_id', $photo->id)->exists());

        $this->actingAs($dentist)->get("/foto/{$photo->id}/original")->assertForbidden(); // tanpa tanda tangan
        $this->flushSession(); // workspace_id di sesi milik dokter pertama; pengguna lain = browser lain
        $this->actingAs($other)->get($photo->url('original'))->assertNotFound(); // workspace lain
        auth()->logout();
        $this->get($photo->url('original'))->assertRedirect('/login');
    }

    public function test_photos_show_capture_timestamp_in_display_timezone(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $session = $this->sessionIn($workspace, Stage::Control, ['control_number' => 1, 'session_date' => '2024-05-01']);

        $this->travelTo(now()->setDateTime(2026, 10, 14, 4, 5)); // 04:05 UTC = 11:05 WIB
        $camera = $this->actingAs($dentist)->postJson("/sesi/{$session->id}/foto", ['file' => $this->jpeg(), 'slot_code' => 'IO-01', 'source' => 'camera'])->json('id');
        $upload = $this->actingAs($dentist)->postJson("/sesi/{$session->id}/foto", [
            'file' => $this->jpeg(['date' => '2024:05:01 09:30:00']), 'slot_code' => 'IO-02', 'source' => 'upload',
        ])->json('id');

        // Foto kamera: UTC diubah ke WIB. Foto unggahan: waktu EXIF (waktu lokal kamera) apa adanya.
        $this->assertSame('14-10-2026 11:05', Photo::withoutGlobalScopes()->find($camera)->timestampText());
        $this->assertSame('01-05-2024 09:30', Photo::withoutGlobalScopes()->find($upload)->timestampText());

        $this->actingAs($dentist)->get("/sesi/{$session->id}")->assertOk()
            ->assertSee('<span class="ts">14-10-2026 11:05</span>', false)
            ->assertSee('data-timestamp-toggle', false)
            ->assertSee("localStorage.getItem(key) !== 'off'", false);
        $this->actingAs($dentist)->get("/foto/{$camera}")->assertOk()->assertSee('14 Okt 2026 11:05');
    }

    public function test_session_camera_and_upload_pages_render(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $session = $this->sessionIn($workspace, Stage::Control, ['control_number' => 3]);

        // Satu-satunya sesi kontrol kasus ini: nomornya menurut urutan tanggal = 1.
        $this->actingAs($dentist)->get("/sesi/{$session->id}")->assertOk()->assertSee('Kontrol ke-1')->assertSee('IO-07')->assertSee('3 slot wajib kosong'); // kontrol: wajib IO-01 s.d. IO-03
        $camera = $this->actingAs($dentist)->get("/sesi/{$session->id}/kamera?slot=IO-04")->assertOk();
        $config = json_decode(str($camera->getContent())->between('id="camera-config">', '</script>'), true);
        $this->assertSame('IO-04', $config['start']);
        $this->assertNotContains('RX-01', array_column($config['slots'], 'code')); // rontgen hanya unggah
        $this->assertNotEmpty($config['slots'][0]['overlay']);
        $this->actingAs($dentist)->get("/sesi/{$session->id}/unggah")->assertOk()->assertSee('RX-01');
    }

    public function test_session_of_another_workspace_is_not_found(): void
    {
        [$dentist] = $this->dentistWithWorkspace();
        [, $other] = $this->dentistWithWorkspace();
        $session = $this->sessionIn($other);

        foreach (["/sesi/{$session->id}", "/sesi/{$session->id}/kamera", "/sesi/{$session->id}/unggah"] as $url) {
            $this->actingAs($dentist)->get($url)->assertNotFound();
        }
        $this->actingAs($dentist)->postJson("/sesi/{$session->id}/foto", ['file' => $this->jpeg(), 'slot_code' => 'IO-01', 'source' => 'camera'])->assertNotFound();
    }
}
