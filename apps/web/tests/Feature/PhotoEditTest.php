<?php

namespace Tests\Feature;

use App\Enums\Stage;
use App\Models\AuditLog;
use App\Models\Photo;
use App\Models\PhotoSession;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Database\Seeders\SlotTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\TestImages;
use Tests\TestCase;

/** FR-13 (edit non-destruktif) dan FR-14 (auto-flip foto cermin). */
class PhotoEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SlotTemplateSeeder::class);
        Storage::fake('photos');
    }

    private function sessionIn(Workspace $workspace): PhotoSession
    {
        $session = app(CurrentWorkspace::class)->run($workspace, fn () => PhotoSession::factory()->create(['stage' => Stage::Control, 'control_number' => 1]));
        app(CurrentWorkspace::class)->set(null);

        return $session;
    }

    private function upload($user, PhotoSession $session, string $slot, string $source = 'upload'): Photo
    {
        $id = $this->actingAs($user)->postJson("/sesi/{$session->id}/foto", [
            'file' => TestImages::upload(TestImages::jpeg(w: 80, h: 60, split: true)), 'slot_code' => $slot, 'source' => $source,
        ])->assertCreated()->json('id');

        return Photo::withoutGlobalScopes()->findOrFail($id);
    }

    private function saveEdit($user, Photo $photo, array $params): TestResponse
    {
        return $this->actingAs($user)->put("/foto/{$photo->id}/ubah", ['params' => json_encode($params)]);
    }

    public function test_uploaded_occlusal_photo_is_auto_flipped_and_original_kept(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $photo = $this->upload($dentist, $this->sessionIn($workspace), 'IO-04');
        $disk = Storage::disk('photos');

        $this->assertTrue($photo->isAutoFlipped());
        $this->assertSame('edited', $photo->displayVariant());
        $this->assertSame('red', TestImages::colorAt($disk->get($photo->original_path), 40, 5));
        $this->assertSame('blue', TestImages::colorAt($disk->get($photo->edited_path), 40, 5));
        $this->assertSame('blue', TestImages::colorAt($disk->get($photo->thumb_path), 40, 5));
        $this->assertSame($photo->original_bytes + $photo->edited_bytes, $workspace->fresh()->used_bytes);
    }

    public function test_camera_and_non_mirror_slots_are_not_auto_flipped(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $session = $this->sessionIn($workspace);

        $this->assertNull($this->upload($dentist, $session, 'IO-04', 'camera')->edited_path); // kamera sudah membalik saat memotret
        $this->assertNull($this->upload($dentist, $session, 'IO-01')->edited_path); // lateral: cermin default mati
    }

    public function test_auto_flip_is_skipped_when_storage_cannot_hold_edited_copy(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $session = $this->sessionIn($workspace);
        $size = strlen(TestImages::jpeg(w: 80, h: 60, split: true));
        $workspace->addUsedBytes($workspace->storage_limit_bytes - $size - 10); // cukup untuk asli saja

        $photo = $this->upload($dentist, $session, 'IO-05');

        $this->assertNull($photo->edited_path);
        $this->assertSame([$photo->original_path, $photo->thumb_path], collect(Storage::disk('photos')->allFiles())->sort()->values()->all());
    }

    public function test_edit_renders_copy_updates_quota_and_reset_restores_original(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $photo = $this->upload($dentist, $this->sessionIn($workspace), 'IO-02');
        $disk = Storage::disk('photos');
        $originalSha = hash('sha256', $disk->get($photo->original_path));

        $this->saveEdit($dentist, $photo, ['rotate' => 90, 'crop' => ['x' => 0, 'y' => 0, 'w' => 0.5, 'h' => 1]])
            ->assertRedirect("/foto/{$photo->id}");

        $photo->refresh();
        $edited = $disk->get($photo->edited_path);
        // 80×60 diputar 90° searah jarum jam = 60×80; separuh kiri (dulu bagian bawah = biru).
        $this->assertSame([30, 80], array_slice(getimagesizefromstring($edited), 0, 2));
        $this->assertSame('blue', TestImages::colorAt($edited, 15, 40));
        $this->assertSame($originalSha, hash('sha256', $disk->get($photo->original_path)));
        $this->assertSame(strlen($edited), $photo->edited_bytes);
        $this->assertSame($photo->original_bytes + $photo->edited_bytes, $workspace->fresh()->used_bytes);
        $this->assertTrue(AuditLog::where('action', 'edit')->where('entity_id', $photo->id)->exists());

        // Edit kedua menggantikan file edit lama.
        $firstEdit = $photo->edited_path;
        $this->saveEdit($dentist, $photo, ['flip_h' => true])->assertRedirect();
        $photo->refresh();
        $disk->assertMissing($firstEdit);
        $disk->assertExists($photo->edited_path);

        $this->actingAs($dentist)->delete("/foto/{$photo->id}/ubah")->assertRedirect("/foto/{$photo->id}");
        $photo->refresh();
        $this->assertNull($photo->edited_path);
        $this->assertNull($photo->edit_params);
        $this->assertSame(0, $photo->edited_bytes);
        $this->assertSame($photo->original_bytes, $workspace->fresh()->used_bytes);
        $this->assertSame([$photo->original_path, $photo->thumb_path], collect($disk->allFiles())->sort()->values()->all());
        $this->assertTrue(AuditLog::where('action', 'edit_reset')->exists());
    }

    public function test_turning_auto_flip_off_keeps_original_orientation(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $photo = $this->upload($dentist, $this->sessionIn($workspace), 'IO-04');

        $this->saveEdit($dentist, $photo, ['flip_v' => false, 'auto_flip' => false])->assertRedirect();

        $photo->refresh();
        $this->assertNull($photo->edited_path);
        $this->assertFalse($photo->isAutoFlipped());
        $this->assertSame(['flip_v' => false, 'auto_flip' => false], array_intersect_key($photo->edit_params, ['flip_v' => 0, 'auto_flip' => 0]));
        $this->assertSame($photo->original_bytes, $workspace->fresh()->used_bytes);
    }

    public function test_invalid_edit_params_are_rejected(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        $photo = $this->upload($dentist, $this->sessionIn($workspace), 'IO-02');

        $this->saveEdit($dentist, $photo, ['straighten' => 30])->assertSessionHasErrors('params');
        $this->saveEdit($dentist, $photo, ['rotate' => 45])->assertSessionHasErrors('params');
        $this->saveEdit($dentist, $photo, ['crop' => ['x' => 0.9, 'y' => 0, 'w' => 0.5, 'h' => 1]])->assertSessionHasErrors('params');
        $this->actingAs($dentist)->put("/foto/{$photo->id}/ubah", ['params' => 'bukan json'])->assertSessionHasErrors('params');

        $this->assertNull($photo->fresh()->edited_path);
    }

    public function test_edit_page_and_edited_file_are_limited_to_own_workspace(): void
    {
        [$dentist, $workspace] = $this->dentistWithWorkspace();
        [$other] = $this->dentistWithWorkspace();
        $photo = $this->upload($dentist, $this->sessionIn($workspace), 'IO-04');

        $this->actingAs($dentist)->get("/foto/{$photo->id}/ubah")->assertOk()->assertSee('editor-config', false);
        $this->actingAs($dentist)->get($photo->url('edited'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($dentist)->get("/foto/{$photo->id}")->assertOk()->assertSee('Dibalik otomatis');

        $this->flushSession();
        $this->actingAs($other)->get("/foto/{$photo->id}/ubah")->assertNotFound();
        $this->saveEdit($other, $photo, ['flip_h' => true])->assertNotFound();
        $this->actingAs($other)->delete("/foto/{$photo->id}/ubah")->assertNotFound();
    }
}
