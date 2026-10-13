<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Workspace;
use App\Support\Dpid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class PatientTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_gets_a_valid_dpid_that_cannot_change(): void
    {
        [, $workspace] = $this->dentistWithWorkspace();
        $this->inWorkspace($workspace);

        $patient = Patient::factory()->create();
        $this->assertMatchesRegularExpression('/^DP-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}$/', $patient->dpid);
        $this->assertTrue(Dpid::isValid($patient->dpid));

        // dpid tidak bisa diisi massal, dan pengisian langsung pun ditolak.
        $patient->update(['dpid' => Dpid::generate()]);
        $this->assertSame($patient->getOriginal('dpid'), $patient->fresh()->dpid);

        $this->expectException(LogicException::class);
        $patient->forceFill(['dpid' => Dpid::generate()])->save();
    }

    public function test_free_workspace_allows_50_patients_and_demo_patients_do_not_count(): void
    {
        [, $workspace] = $this->dentistWithWorkspace();
        $this->inWorkspace($workspace);

        Patient::factory()->count(49)->create();
        Patient::factory()->create(['is_demo' => true]);
        $this->assertTrue($workspace->canAddPatient());

        Patient::factory()->create();
        $this->assertFalse($workspace->canAddPatient());
    }

    public function test_premium_workspace_has_no_limits(): void
    {
        $workspace = Workspace::factory()->premium()->create();

        $this->assertTrue($workspace->canAddPatient());
        $this->assertTrue($workspace->hasStorageFor(100 * 1024 ** 3));
        $this->assertNull($workspace->storageUsedPercent());
    }

    public function test_free_storage_limit_is_5_gb(): void
    {
        $workspace = Workspace::factory()->create();
        $workspace->addUsedBytes(4 * 1024 ** 3);

        $this->assertSame(80, $workspace->storageUsedPercent());
        $this->assertTrue($workspace->hasStorageFor(1024 ** 3));
        $this->assertFalse($workspace->hasStorageFor(1024 ** 3 + 1));
    }
}
