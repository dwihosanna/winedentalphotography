<?php

namespace App\Models;

use App\Enums\Stage;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Case report / report progres (FR-20, FR-21, FR-26). PDF disimpan sebagai snapshot. */
#[Fillable(['ortho_case_id', 'template', 'before_session_id', 'after_session_id', 'selected_photos', 'anonymized', 'show_timestamp', 'created_by'])]
class Report extends Model
{
    use BelongsToWorkspace, HasUuids;

    protected function casts(): array
    {
        return ['selected_photos' => 'array', 'anonymized' => 'boolean', 'show_timestamp' => 'boolean', 'pdf_bytes' => 'integer'];
    }

    public function orthoCase(): BelongsTo
    {
        return $this->belongsTo(OrthoCase::class);
    }

    public function beforeSession(): BelongsTo
    {
        return $this->belongsTo(PhotoSession::class, 'before_session_id');
    }

    public function afterSession(): BelongsTo
    {
        return $this->belongsTo(PhotoSession::class, 'after_session_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Nama file unduhan/bagikan: Report-Nama Pasien-dd-mm-yyyy.pdf (report anonim: Report-Anonim-...). */
    public function pdfFileName(Patient $patient): string
    {
        $name = $this->anonymized ? 'Anonim' : $patient->name;
        // Ganti karakter yang tidak boleh ada di nama file (Windows/iOS) dengan spasi, lalu rapikan spasi.
        $name = trim(preg_replace('/\s+/u', ' ', preg_replace('~[\\/:*?"<>|\x00-\x1F]~u', ' ', $name)));

        return 'Report-'.(mb_substr($name, 0, 80) ?: 'Pasien').'-'.$this->created_at->format('d-m-Y').'.pdf';
    }

    /** Parameter untuk membuka preview presentasi dengan pilihan foto report ini. */
    public function presentationQuery(): array
    {
        return [
            'ortho_case_id' => $this->ortho_case_id,
            'before_session_id' => $this->before_session_id,
            'after_session_id' => $this->after_session_id,
            'slots' => collect($this->selected_photos)->map(fn (array $pair) => ['include' => 1] + $pair)->all(),
        ];
    }

    /** "Before / After" bila sesi sesudah = Lepas/Retensi/Sesudah; selain itu report progres (FR-26). */
    public function title(): string
    {
        return in_array($this->afterSession?->stage, [Stage::Debond, Stage::Retention, Stage::After], true) ? 'Before / After' : 'Report Progres';
    }
}
