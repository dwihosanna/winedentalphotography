<?php

namespace Tests\Feature;

use App\Enums\ConsentType;
use App\Enums\Stage;
use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\OrthoCase;
use App\Models\Patient;
use App\Models\Photo;
use App\Models\PhotoSession;
use App\Models\Report;
use App\Models\User;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Database\Seeders\SlotTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestImages;
use Tests\TestCase;

/** FR-21 (report before/after → PDF A4), FR-22 (anonim butuh consent publikasi), FR-23 (hanya dokter). */
class ReportTest extends TestCase
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

    /** @return array{0: Patient, 1: OrthoCase, 2: PhotoSession, 3: PhotoSession} */
    private function caseWithTwoSessions(Workspace $workspace, User $dentist): array
    {
        [$patient, $case, $before, $after] = $this->in($workspace, function () use ($dentist) {
            $patient = Patient::factory()->create(['name' => 'Ayu Setiawati']);
            $case = OrthoCase::factory()->create(['patient_id' => $patient->id, 'dentist_user_id' => $dentist->id, 'brace_type' => 'Metal']);
            $session = fn (Stage $stage, string $date) => PhotoSession::factory()->create([
                'ortho_case_id' => $case->id, 'stage' => $stage, 'session_date' => $date, 'created_by' => $dentist->id,
            ]);

            return [$patient, $case, $session(Stage::Initial, '2025-01-10'), $session(Stage::Debond, '2026-07-10')];
        });

        foreach ([$before, $after] as $session) {
            foreach (['EO-01', 'IO-01', 'IO-04'] as $slot) {
                $this->upload($dentist, $session, $slot);
            }
        }

        return [$patient, $case, $before, $after];
    }

    private function upload(User $user, PhotoSession $session, string $slot): string
    {
        return $this->actingAs($user)->postJson("/sesi/{$session->id}/foto", [
            'file' => TestImages::upload(TestImages::jpeg(w: 320, h: 240, split: true)), 'slot_code' => $slot, 'source' => 'camera',
        ])->assertCreated()->json('id');
    }

    private function photoId(PhotoSession $session, string $slot): string
    {
        return Photo::withoutGlobalScopes()->where('photo_session_id', $session->id)->where('slot_code', $slot)->whereNull('replaced_by_photo_id')->value('id');
    }

    /** @return array<string, mixed> */
    private function form(OrthoCase $case, PhotoSession $before, PhotoSession $after, array $extra = []): array
    {
        $slots = [];
        foreach (['EO-01', 'IO-01', 'IO-04'] as $slot) {
            $slots[$slot] = ['include' => 1, 'before' => $this->photoId($before, $slot), 'after' => $this->photoId($after, $slot)];
        }

        return array_replace_recursive([
            'ortho_case_id' => $case->id, 'before_session_id' => $before->id, 'after_session_id' => $after->id, 'slots' => $slots,
        ], $extra);
    }

    /** Tangkap data view PDF lalu render ulang sebagai HTML; teks di PDF dompdf terkompresi. */
    private function capturePdfHtml(): \Closure
    {
        $html = null;
        $rendering = false;
        Event::listen('composing: reports.pdf', function ($view) use (&$html, &$rendering) {
            if ($rendering) {
                return;
            }
            $rendering = true;
            $html = view('reports.pdf', $view->getData())->render();
            $rendering = false;
        });

        return function () use (&$html) {
            return $html;
        };
    }

    public function test_dentist_previews_and_generates_pdf_snapshot(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        [$patient, $case, $before, $after] = $this->caseWithTwoSessions($workspace, $dentist);
        $this->upload($dentist, $before, 'IO-01'); // retake: versi lama tetap bisa dipilih

        $this->actingAs($dentist)->get("/pasien/{$patient->id}/report/baru")->assertOk()
            ->assertViewHas('before', fn ($s) => $s->is($before))
            ->assertViewHas('after', fn ($s) => $s->is($after))
            ->assertSee('IO-04')->assertSee('Retake lama');

        $html = $this->capturePdfHtml();
        $usedBefore = $workspace->fresh()->used_bytes;
        $res = $this->actingAs($dentist)->post("/pasien/{$patient->id}/report", $this->form($case, $before, $after, ['slots' => ['IO-04' => ['include' => 0]]]));

        $report = Report::withoutGlobalScopes()->sole();
        $res->assertRedirect("/report/{$report->id}");
        $this->assertSame(['EO-01', 'IO-01'], array_keys($report->selected_photos));
        $this->assertStringStartsWith('%PDF', Storage::disk('photos')->get($report->pdf_path));
        $this->assertSame($usedBefore + $report->pdf_bytes, $workspace->fresh()->used_bytes);
        $this->assertStringContainsString('Ayu Setiawati', $html());
        $this->assertStringContainsString('Before / After', $html());
        $this->assertStringNotContainsString('IO-04', $html());

        $this->actingAs($dentist)->get("/report/{$report->id}")->assertOk()->assertSee('Buka PDF');
        $this->actingAs($dentist)->get("/report/{$report->id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertTrue(AuditLog::where('action', 'create')->where('entity_id', $report->id)->exists());
        $this->assertTrue(AuditLog::where('action', 'export')->where('entity_id', $report->id)->exists());
    }

    public function test_presentation_preview_shows_selection_without_creating_report(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        [$patient, $case, $before, $after] = $this->caseWithTwoSessions($workspace, $dentist);
        $query = http_build_query($this->form($case, $before, $after, ['slots' => ['IO-04' => ['include' => 0]]]));

        $res = $this->actingAs($dentist)->get("/pasien/{$patient->id}/report/presentasi?{$query}")->assertOk()
            ->assertSee('Presentasi · Ayu Setiawati')->assertSee('Before / After');

        $slides = $res->viewData('slides');
        $this->assertSame(['EO-01', 'IO-01'], $slides->pluck('code')->all());
        $this->assertSame('Awal', $slides[0]['before']['stage']);
        $this->assertSame('Lepas', $slides[0]['after']['stage']);
        // URL foto bertanda tangan, berlaku ±60 menit untuk presentasi.
        parse_str(parse_url($slides[0]['after']['url'], PHP_URL_QUERY), $params);
        $this->assertEqualsWithDelta(now()->addMinutes(60)->timestamp, (int) $params['expires'], 5);
        $this->actingAs($dentist)->get($slides[0]['after']['url'])->assertOk();

        $this->assertSame(0, Report::withoutGlobalScopes()->count());
        $this->assertTrue(AuditLog::where('action', 'present')->where('entity_id', $patient->id)->exists());
    }

    public function test_saved_report_links_to_presentation_and_assistant_cannot_present(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        [$patient, $case, $before, $after] = $this->caseWithTwoSessions($workspace, $dentist);
        $this->actingAs($dentist)->post("/pasien/{$patient->id}/report", $this->form($case, $before, $after))->assertRedirect();
        $report = Report::withoutGlobalScopes()->sole();

        $link = route('reports.present', ['patient' => $patient] + $report->presentationQuery());
        $this->actingAs($dentist)->get("/report/{$report->id}")->assertSee(e($link), false);
        $this->actingAs($dentist)->get($link)->assertOk()->assertViewHas('slides', fn ($slides) => $slides->count() === 3);

        $this->flushSession();
        $this->actingAs($this->assistantIn($workspace))->get($link)->assertForbidden();
    }

    public function test_anonymized_report_requires_publication_consent_and_hides_identity(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        [$patient, $case, $before, $after] = $this->caseWithTwoSessions($workspace, $dentist);

        $this->actingAs($dentist)->post("/pasien/{$patient->id}/report", $this->form($case, $before, $after, ['anonymized' => 1]))
            ->assertSessionHasErrors('anonymized');
        $this->assertSame(0, Report::withoutGlobalScopes()->count());

        $this->in($workspace, fn () => Consent::create([
            'patient_id' => $patient->id, 'type' => ConsentType::Publication, 'given_on' => '2025-01-10', 'recorded_by' => $dentist->id,
        ]));
        $html = $this->capturePdfHtml();
        $this->actingAs($dentist)->post("/pasien/{$patient->id}/report", $this->form($case, $before, $after, ['anonymized' => 1]))
            ->assertRedirect();

        $this->assertTrue(Report::withoutGlobalScopes()->sole()->anonymized);
        $this->assertStringNotContainsString('Ayu Setiawati', $html());
        $this->assertStringNotContainsString($patient->dpid, $html());
        $this->assertStringNotContainsString('10-01-2025', $html());
        $this->assertStringContainsString('18 bulan', $html());
    }

    public function test_selection_must_match_chosen_sessions(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        [$patient, $case, $before, $after] = $this->caseWithTwoSessions($workspace, $dentist);
        $url = "/pasien/{$patient->id}/report";

        // Foto "sesudah" dipakai sebagai "sebelum".
        $this->actingAs($dentist)->post($url, $this->form($case, $before, $after, ['slots' => ['IO-01' => ['before' => $this->photoId($after, 'IO-01')]]]))
            ->assertSessionHasErrors('slots');
        $this->actingAs($dentist)->post($url, $this->form($case, $before, $before))->assertSessionHasErrors('after_session_id');
        $this->actingAs($dentist)->post($url, $this->form($case, $before, $after, ['slots' => [
            'EO-01' => ['include' => 0], 'IO-01' => ['include' => 0], 'IO-04' => ['include' => 0],
        ]]))->assertSessionHasErrors('slots');

        $this->assertSame(0, Report::withoutGlobalScopes()->count());
    }

    public function test_only_dentist_can_create_or_export_reports(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        [$patient, $case, $before, $after] = $this->caseWithTwoSessions($workspace, $dentist);
        $this->actingAs($dentist)->post("/pasien/{$patient->id}/report", $this->form($case, $before, $after))->assertRedirect();
        $report = Report::withoutGlobalScopes()->sole();
        $assistant = $this->assistantIn($workspace);

        $this->flushSession();
        $this->actingAs($assistant)->get("/pasien/{$patient->id}")->assertOk()->assertDontSee('Buat report');
        $this->actingAs($assistant)->get("/pasien/{$patient->id}/report/baru")->assertForbidden();
        $this->actingAs($assistant)->post("/pasien/{$patient->id}/report", $this->form($case, $before, $after))->assertForbidden();
        $this->actingAs($assistant)->get("/report/{$report->id}/pdf")->assertForbidden();
    }

    public function test_reports_are_limited_to_own_workspace(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        [$other] = $this->dentistWithWorkspace();
        [$patient, $case, $before, $after] = $this->caseWithTwoSessions($workspace, $dentist);
        $this->actingAs($dentist)->post("/pasien/{$patient->id}/report", $this->form($case, $before, $after))->assertRedirect();
        $report = Report::withoutGlobalScopes()->sole();

        $this->flushSession();
        $this->actingAs($other)->get("/report/{$report->id}")->assertNotFound();
        $this->actingAs($other)->get("/report/{$report->id}/pdf")->assertNotFound();
        $this->actingAs($other)->get("/pasien/{$patient->id}/report/baru")->assertNotFound();
    }
}
