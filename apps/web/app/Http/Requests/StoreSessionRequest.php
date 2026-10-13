<?php

namespace App\Http\Requests;

use App\Enums\Stage;
use App\Models\OrthoCase;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sesi foto baru (FR-05). Tanggal bisa di masa lalu agar foto lama pasien bisa diarsipkan per kunjungan.
 * Kasus dipilih dari kasus aktif, atau dibuat sekaligus (FR-04).
 */
class StoreSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Membuat kasus baru hanya dokter (SRS Bagian 4); sesi bisa dibuat asisten.
        return $this->input('ortho_case_id') !== 'new' || app(CurrentWorkspace::class)->isDentist();
    }

    public function rules(): array
    {
        $patient = $this->route('patient');
        $newCase = $this->input('ortho_case_id') === 'new';

        return [
            'ortho_case_id' => ['required', $newCase ? 'in:new' : Rule::exists(OrthoCase::class, 'id')
                ->where('workspace_id', app(CurrentWorkspace::class)->id())->where('patient_id', $patient->id)],
            'brace_type' => [$newCase ? 'required' : 'nullable', 'string', 'max:64'],
            'installed_on' => ['nullable', 'date', 'before_or_equal:today'],
            'stage' => ['required', Rule::enum(Stage::class)],
            'session_date' => ['required', 'date', 'before_or_equal:today', 'after:2000-01-01'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'ortho_case_id' => 'kasus', 'brace_type' => 'tipe behel', 'installed_on' => 'tanggal pemasangan',
            'stage' => 'tahap', 'session_date' => 'tanggal sesi', 'notes' => 'keterangan',
        ];
    }
}
