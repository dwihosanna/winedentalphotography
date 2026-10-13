<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_is_append_only(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $this->inWorkspace($workspace);
        $this->actingAs($dentist);

        $log = AuditLog::record('create', Patient::factory()->create());
        $this->assertSame($workspace->id, $log->workspace_id);
        $this->assertSame($dentist->id, $log->user_id);

        $this->expectException(LogicException::class);
        $log->update(['action' => 'x']);
    }

    public function test_audit_log_cannot_be_deleted(): void
    {
        [, $workspace] = $this->dentistWithWorkspace();
        $this->inWorkspace($workspace);
        $log = AuditLog::record('create', Patient::factory()->create());

        $this->expectException(LogicException::class);
        $log->delete();
    }
}
