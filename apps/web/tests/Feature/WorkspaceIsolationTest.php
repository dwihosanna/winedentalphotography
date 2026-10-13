<?php

namespace Tests\Feature;

use App\Models\OrthoCase;
use App\Models\Patient;
use App\Support\CurrentWorkspace;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use LogicException;
use Tests\TestCase;

/** Pengganti row-level security MySQL: data satu workspace tidak boleh terbaca atau tertaut dari workspace lain. */
class WorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_queries_only_see_the_current_workspace(): void
    {
        [, $a] = $this->dentistWithWorkspace();
        [, $b] = $this->dentistWithWorkspace();

        $this->inWorkspace($a);
        $mine = Patient::factory()->create();
        $this->inWorkspace($b);
        $theirs = Patient::factory()->create();

        $this->inWorkspace($a);
        $this->assertSame([$mine->id], Patient::pluck('id')->all());
        $this->assertNull(Patient::find($theirs->id));
    }

    public function test_query_without_workspace_fails_closed(): void
    {
        app(CurrentWorkspace::class)->set(null);

        $this->expectException(LogicException::class);
        Patient::count();
    }

    public function test_creating_sets_workspace_and_rejects_another_workspace(): void
    {
        [, $a] = $this->dentistWithWorkspace();
        [, $b] = $this->dentistWithWorkspace();
        $this->inWorkspace($a);

        $this->assertSame($a->id, Patient::factory()->create()->workspace_id);

        $this->expectException(LogicException::class);
        $patient = Patient::factory()->make();
        $patient->workspace_id = $b->id;
        $patient->save();
    }

    public function test_database_rejects_case_pointing_to_patient_of_another_workspace(): void
    {
        [$dentist, $a] = $this->dentistWithWorkspace();
        [, $b] = $this->dentistWithWorkspace();
        $this->inWorkspace($b);
        $theirs = Patient::factory()->create();

        // Bahkan bila scope aplikasi dilewati, foreign key gabungan (workspace_id, patient_id) menolak.
        $this->expectException(QueryException::class);
        DB::table('ortho_cases')->insert([
            'id' => (string) str()->uuid(), 'workspace_id' => $a->id, 'patient_id' => $theirs->id,
            'brace_type' => 'Metal', 'dentist_user_id' => $dentist->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_route_binding_returns_404_for_patient_of_another_workspace(): void
    {
        Route::middleware(['web', 'auth', 'workspace'])->get('/_test/patients/{patient}', fn (Patient $patient) => $patient->dpid);

        [$dentist, $a] = $this->dentistWithWorkspace();
        [, $b] = $this->dentistWithWorkspace();
        $this->inWorkspace($a);
        $mine = Patient::factory()->create();
        $this->inWorkspace($b);
        $theirs = Patient::factory()->create();
        app(CurrentWorkspace::class)->set(null);

        $this->actingAs($dentist)->get("/_test/patients/{$mine->id}")->assertOk()->assertSee($mine->dpid);
        $this->actingAs($dentist)->get("/_test/patients/{$theirs->id}")->assertNotFound();
    }

    public function test_user_cannot_switch_to_a_workspace_they_do_not_belong_to(): void
    {
        Route::middleware(['web', 'auth', 'workspace'])->get('/_test/ping', fn () => 'ok');

        [$dentist] = $this->dentistWithWorkspace();
        [, $other] = $this->dentistWithWorkspace();

        $this->actingAs($dentist)->withSession(['workspace_id' => $other->id])->get('/_test/ping')->assertForbidden();
    }

    public function test_suspended_workspace_is_read_only(): void
    {
        Route::middleware(['web', 'auth', 'workspace'])->match(['get', 'post'], '/_test/write', fn () => 'ok');

        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $workspace->update(['status' => 'suspended']);

        $this->actingAs($dentist)->get('/_test/write')->assertOk();
        $this->actingAs($dentist)->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/_test/write')->assertForbidden();
    }

    public function test_child_records_follow_the_parent_workspace(): void
    {
        [, $a] = $this->dentistWithWorkspace();
        $this->inWorkspace($a);

        $case = OrthoCase::factory()->create();
        $this->assertSame($a->id, $case->workspace_id);
        $this->assertSame($a->id, $case->patient->workspace_id);
    }
}
