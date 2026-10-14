<?php

namespace App\Http\Requests;

use App\Enums\CaseKind;
use App\Enums\Stage;
use App\Models\OrthoCase;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sesi foto baru (FR-05). Tanggal bisa di masa lalu agar foto lama pasien bisa diarsipkan per kunjungan.
 * Kasus dipilih dari kasus aktif, atau dibuat sekaligus (FR-04): ortho (tipe behel) atau non-ortho (jenis perawatan).
 * Tahap harus sesuai jenis kasus: ortho Awal s.d. Retensi, non-ortho Sebelum/Sesudah.
 */
class StoreSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Membuat kasus baru hanya dokter (SRS Bagian 4); sesi bisa dibuat asisten.
        return ! $this->isNewCase() || app(CurrentWorkspace::class)->isDentist();
    }

    public function rules(): array
    {
        $patient = $this->route('patient');
        $newCase = $this->isNewCase();
        $kind = $this->caseKind();

        return [
            'ortho_case_id' => ['required', $newCase ? 'in:new' : Rule::exists(OrthoCase::class, 'id')
                ->where('workspace_id', app(CurrentWorkspace::class)->id())->where('patient_id', $patient->id)],
            'case_kind' => [$newCase ? 'required' : 'nullable', Rule::enum(CaseKind::class)],
            'brace_type' => [Rule::requiredIf($newCase && $kind === CaseKind::Ortho), 'nullable', Rule::in([...config('dental.brace_types'), 'other'])],
            'brace_type_other' => ['required_if:brace_type,other', 'nullable', 'string', 'max:64'],
            'treatment_type' => [Rule::requiredIf($newCase && $kind === CaseKind::General), 'nullable', Rule::in([...config('dental.treatment_types'), 'other'])],
            'treatment_type_other' => ['required_if:treatment_type,other', 'nullable', 'string', 'max:64'],
            'installed_on' => ['nullable', 'date', 'before_or_equal:today'],
            'stage' => ['required', Rule::enum(Stage::class)->only($kind?->stages() ?? Stage::cases())],
            'session_date' => ['required', 'date', 'before_or_equal:today', 'after:2000-01-01'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'stage.enum' => 'Tahap ini tidak berlaku untuk jenis kasus yang dipilih.',
            'brace_type_other.required_if' => 'Isi tipe behel lainnya.',
            'treatment_type_other.required_if' => 'Isi jenis perawatan lainnya.',
        ];
    }

    public function attributes(): array
    {
        return [
            'ortho_case_id' => 'kasus', 'case_kind' => 'jenis kasus', 'brace_type' => 'tipe behel',
            'treatment_type' => 'jenis perawatan', 'installed_on' => 'tanggal pemasangan',
            'stage' => 'tahap', 'session_date' => 'tanggal sesi', 'notes' => 'keterangan',
        ];
    }

    public function isNewCase(): bool
    {
        return $this->input('ortho_case_id') === 'new';
    }

    /** Jenis kasus: dari pilihan form (kasus baru) atau dari kasus yang dipilih. */
    public function caseKind(): ?CaseKind
    {
        if ($this->isNewCase()) {
            return CaseKind::tryFrom((string) $this->input('case_kind'));
        }

        return OrthoCase::find($this->input('ortho_case_id'))?->kind;
    }

    /**
     * Atribut kasus baru; "Lainnya" diganti teks isian.
     *
     * @return array{kind: CaseKind, brace_type: ?string, treatment_type: ?string, installed_on: ?string}
     */
    public function caseAttributes(): array
    {
        $data = $this->validated();
        $kind = CaseKind::from($data['case_kind']);
        $pick = fn (string $field) => ($data[$field] ?? null) === 'other' ? trim($data["{$field}_other"]) : ($data[$field] ?? null);

        return [
            'kind' => $kind,
            'brace_type' => $kind === CaseKind::Ortho ? $pick('brace_type') : null,
            'treatment_type' => $kind === CaseKind::General ? $pick('treatment_type') : null,
            'installed_on' => $kind === CaseKind::Ortho ? ($data['installed_on'] ?? null) : null,
        ];
    }
}
