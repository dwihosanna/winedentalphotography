<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** FR-01, FR-02, FR-30, FR-31, FR-32, FR-34 lewat HTTP. */
class PatientPagesTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $overrides = []): array
    {
        return $overrides + ['name' => 'Ayu  Setiawati', 'birth_date' => '1998-02-03', 'phone' => '0812-0000-0000', 'rm' => 'RM-001'];
    }

    /** Buat pasien di workspace lalu kosongkan konteks agar request berikutnya menetapkannya sendiri. */
    private function patientIn($workspace, array $attributes = []): Patient
    {
        $patient = app(CurrentWorkspace::class)->run($workspace, fn () => Patient::factory()->create($attributes));
        app(CurrentWorkspace::class)->set(null);

        return $patient;
    }

    public function test_dentist_creates_patient_with_dpid_rm_and_audit_log(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();

        $response = $this->actingAs($dentist)->post('/pasien', $this->form());

        $patient = Patient::withoutGlobalScopes()->firstOrFail();
        $response->assertRedirect("/pasien/{$patient->id}");
        $this->assertSame('Ayu Setiawati', $patient->name);
        $this->assertSame('081200000000', $patient->phone);
        $this->assertSame($workspace->id, $patient->workspace_id);
        $this->assertSame('RM-001', $patient->externalIds()->withoutGlobalScopes()->first()->value);
        $this->assertTrue(AuditLog::where('action', 'create')->where('entity_id', $patient->id)->exists());

        $this->actingAs($dentist)->get("/pasien/{$patient->id}")->assertOk()->assertSee($patient->dpid)->assertSee('RM-001');
        $this->assertTrue(AuditLog::where('action', 'view')->where('entity_id', $patient->id)->exists());
    }

    public function test_contact_is_required(): void
    {
        [$dentist] = $this->dentistWithWorkspace();

        $this->actingAs($dentist)->post('/pasien', $this->form(['phone' => null]))->assertSessionHasErrors(['phone', 'email']);
    }

    public function test_rm_is_unique_per_workspace_only(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        [$other] = $this->dentistWithWorkspace();

        $this->actingAs($dentist)->post('/pasien', $this->form())->assertSessionHasNoErrors();
        $this->actingAs($dentist)->post('/pasien', $this->form(['name' => 'Budi', 'rm' => 'RM-001']))->assertSessionHasErrors('rm');
        $this->actingAs($other)->post('/pasien', $this->form(['rm' => 'RM-001']))->assertSessionHasNoErrors();
    }

    public function test_same_name_and_birth_date_asks_for_confirmation(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $this->patientIn($workspace, ['name' => 'Ayu Setiawati', 'birth_date' => '1998-02-03']);

        $this->actingAs($dentist)->post('/pasien', $this->form(['rm' => null]))->assertSessionHas('duplicates');
        $this->assertSame(1, Patient::withoutGlobalScopes()->count());

        $this->actingAs($dentist)->post('/pasien', $this->form(['rm' => null, 'confirm_duplicate' => '1']))->assertRedirect();
        $this->assertSame(2, Patient::withoutGlobalScopes()->count());
    }

    public function test_free_workspace_rejects_patient_51(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        app(CurrentWorkspace::class)->run($workspace, fn () => Patient::factory()->count(50)->create());
        app(CurrentWorkspace::class)->set(null);

        $this->actingAs($dentist)->post('/pasien', $this->form())->assertSessionHasErrors('quota');
        $this->assertSame(50, Patient::withoutGlobalScopes()->count());
    }

    public function test_update_keeps_dpid_and_logs_field_names_only(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $patient = $this->patientIn($workspace);

        $this->actingAs($dentist)->put("/pasien/{$patient->id}", $this->form(['dpid' => 'DP-0000-0000']))->assertRedirect();

        $fresh = Patient::withoutGlobalScopes()->find($patient->id);
        $this->assertSame($patient->dpid, $fresh->dpid);
        $this->assertSame('Ayu Setiawati', $fresh->name);
        $log = AuditLog::where('action', 'update')->firstOrFail();
        $this->assertContains('name', $log->meta['fields']);
        $this->assertContains('rm', $log->meta['fields']);
        $this->assertStringNotContainsString('Ayu', json_encode($log->meta));
    }

    public function test_dashboard_lists_patients_grouped_and_search_fallback_works(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $ayu = $this->patientIn($workspace, ['name' => 'Ayu Setiawati', 'phone' => '081211112222']);
        $this->patientIn($workspace, ['name' => 'Budi Pratama']);

        $this->actingAs($dentist)->get('/pasien')->assertOk()
            ->assertSeeInOrder(['huruf-A', 'Setiawati', 'huruf-B', 'Pratama'], false);

        $this->actingAs($dentist)->get('/pasien?q='.urlencode('0812 1111'))->assertSee('Setiawati')->assertDontSee('Pratama');
        $this->actingAs($dentist)->get('/pasien?q='.strtolower($ayu->dpid))->assertSee('Setiawati')->assertDontSee('Pratama');
    }

    public function test_assistant_can_view_but_not_create_or_edit(): void
    {
        [, $workspace] = $this->dentistWithWorkspace();
        $assistant = $this->assistantIn($workspace);
        $patient = $this->patientIn($workspace);

        $this->actingAs($assistant)->get('/pasien')->assertOk()->assertDontSee('Tambah pasien');
        $this->actingAs($assistant)->get("/pasien/{$patient->id}")->assertOk();
        $this->actingAs($assistant)->get('/pasien/baru')->assertForbidden();
        $this->actingAs($assistant)->post('/pasien', $this->form())->assertForbidden();
        $this->actingAs($assistant)->get("/pasien/{$patient->id}/ubah")->assertForbidden();
    }

    public function test_patient_of_another_workspace_is_not_found(): void
    {
        [$dentist] = $this->dentistWithWorkspace();
        [, $other] = $this->dentistWithWorkspace();
        $theirs = $this->patientIn($other);

        $this->actingAs($dentist)->get("/pasien/{$theirs->id}")->assertNotFound();
        $this->actingAs($dentist)->put("/pasien/{$theirs->id}", $this->form())->assertNotFound();
        $this->actingAs($dentist)->get('/pasien')->assertDontSee($theirs->dpid);
    }

    public function test_all_pages_render(): void
    {
        foreach (['/login', '/register', '/forgot-password', '/reset-password/token?email=a@b.c'] as $url) {
            $this->get($url)->assertOk();
        }

        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $patient = $this->patientIn($workspace);

        $this->actingAs($dentist)->get('/pasien')->assertOk()->assertSee('Pasien')->assertSee('1 / 50', false);
        $this->actingAs($dentist)->get('/pasien/baru')->assertOk()->assertSee('Simpan pasien');
        $this->actingAs($dentist)->get("/pasien/{$patient->id}/ubah")->assertOk()->assertSee($patient->dpid);
    }

    public function test_footer_shows_app_version(): void
    {
        config(['dental.version' => '1.23']);

        $this->get('/login')->assertOk()->assertSee('© 2026 MarikhTech x Wine Dental Aesthetic · Version 1.23');
    }

    public function test_pinch_zoom_is_locked_in_installed_app_except_pdf_viewer(): void
    {
        $this->get('/login')->assertOk()->assertSee("matchMedia('(display-mode: standalone)')", false)->assertSee('gesturestart', false);
    }

    public function test_pages_are_installable_as_pwa(): void
    {
        $this->get('/login')->assertSee('rel="manifest" href="/manifest.webmanifest"', false)
            ->assertSee('apple-touch-icon', false)
            ->assertSee('rel="icon" href="/icons/iconnew.svg"', false);
        $this->assertFileExists(public_path('icons/iconnew.svg'));
        $this->assertFileExists(public_path('icons/favicon-32.png'));

        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);
        $this->assertSame('standalone', $manifest['display']);
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('offline.html'));
    }
}
