<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\OrthoCase;
use App\Models\Photo;
use App\Models\PhotoSession;
use App\Models\Report;
use App\Models\Workspace;
use App\Support\JpegMetadata;
use App\Support\ReleaseDatabase;
use App\Support\Thumbnail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Report before/after atau progres (FR-21, FR-26) sebagai PDF A4 snapshot (NFR-08).
 * Foto memakai versi tampil (hasil edit bila ada) dan diperkecil agar ±150 dpi saat dicetak.
 */
class GenerateReport
{
    public const TEMPLATE = 'before_after';

    /** Sisi terpanjang foto di PDF: kolom ±90 mm × 150 dpi ≈ 530 px, dikali 2 agar tetap tajam saat di-zoom. */
    private const IMAGE_SIZE = 1100;

    /**
     * @param  array<string, array{before: ?string, after: ?string}>  $selection  slot → id foto (sudah divalidasi)
     */
    public function __invoke(OrthoCase $case, PhotoSession $before, PhotoSession $after, array $selection, bool $anonymized, int $userId): Report
    {
        $photos = Photo::with('session')->whereIn('id', collect($selection)->flatten()->filter())->get()->keyBy('id');
        $slots = config('slots.slots');
        $case->loadMissing('patient.externalIds', 'dentist');
        $patient = $case->patient;
        $workspace = Workspace::find($case->workspace_id);
        // Semua data sudah dimuat; olah foto + render PDF (beberapa detik) tanpa menahan koneksi MySQL yang dibatasi hosting.
        ReleaseDatabase::now();

        $rows = collect($selection)
            ->sortBy(fn ($_, $code) => array_search($code, array_keys($slots), true))
            ->map(fn (array $pair, string $code) => [
                'code' => $code,
                'label' => $slots[$code]['label'],
                'before' => $this->image($photos->get($pair['before'] ?? '')),
                'after' => $this->image($photos->get($pair['after'] ?? '')),
            ])->values();

        $report = new Report([
            'ortho_case_id' => $case->id,
            'template' => self::TEMPLATE,
            'before_session_id' => $before->id,
            'after_session_id' => $after->id,
            'selected_photos' => $selection,
            'anonymized' => $anonymized,
            'created_by' => $userId,
        ]);
        $report->id = (string) Str::uuid();
        $report->setRelation('afterSession', $after);

        $pdf = Pdf::loadView('reports.pdf', [
            'report' => $report,
            'case' => $case,
            'patient' => $patient,
            'before' => $before,
            'after' => $after,
            'rows' => $rows,
            'workspace' => $workspace,
            'rm' => $patient->externalIds->firstWhere('system', config('dental.default_rm_system'))?->value,
        ])->setPaper('a4')->output();

        $disk = config('dental.photo_disk');
        $path = "{$case->workspace_id}/reports/{$report->id}.pdf";
        Storage::disk($disk)->put($path, $pdf);

        try {
            return DB::transaction(function () use ($report, $pdf, $disk, $path, $case) {
                $workspace = Workspace::whereKey($case->workspace_id)->lockForUpdate()->first();
                if (! $workspace->hasStorageFor(strlen($pdf))) {
                    throw ValidationException::withMessages(['report' => 'Penyimpanan penuh: PDF report tidak bisa disimpan.']);
                }

                $report->forceFill(['disk' => $disk, 'pdf_path' => $path, 'pdf_bytes' => strlen($pdf)])->save();
                $workspace->addUsedBytes(strlen($pdf));
                AuditLog::record('create', $report, ['anonymized' => $report->anonymized, 'slots' => array_keys($report->selected_photos)]);

                return $report;
            });
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }
    }

    /** @return array{src: string, width_mm: float, height_mm: float, date: string, stage: string}|null */
    private function image(?Photo $photo): ?array
    {
        if ($photo === null) {
            return null;
        }

        $disk = Storage::disk($photo->disk);
        $edited = $photo->edited_path !== null;
        $bytes = $disk->get($edited ? $photo->edited_path : $photo->original_path);
        $orientation = ! $edited && $photo->mime === 'image/jpeg' ? JpegMetadata::orientationOf($bytes) : 1;
        $jpeg = Thumbnail::make($bytes, $orientation, self::IMAGE_SIZE, 85)
            ?? throw ValidationException::withMessages(['report' => "Foto {$photo->slot_code} tidak bisa dibaca."]);

        [$w, $h] = getimagesizefromstring($jpeg);
        // Kotak foto di PDF 88 × 74 mm (3 baris per halaman A4); ukuran eksplisit karena dompdf tidak andal dengan max-height.
        $scale = min(88 / $w, 74 / $h);

        return [
            'src' => 'data:image/jpeg;base64,'.base64_encode($jpeg),
            'width_mm' => round($w * $scale, 1),
            'height_mm' => round($h * $scale, 1),
            'date' => $photo->session->session_date->format('d-m-Y'),
            'stage' => $photo->session->stageLabel(),
        ];
    }
}
