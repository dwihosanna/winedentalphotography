<?php

namespace App\Http\Requests;

use App\Models\Patient;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Sheet pasien (FR-02): wajib nama + tanggal lahir + salah satu kontak; No. RM bila diisi unik per workspace. */
class PatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $patient = $this->route('patient');

        return $patient instanceof Patient
            ? $this->user()->can('update', $patient)
            : $this->user()->can('create', Patient::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim(preg_replace('/\s+/', ' ', (string) $this->input('name'))),
            'email' => $this->filled('email') ? strtolower(trim($this->input('email'))) : null,
            'phone' => $this->filled('phone') ? preg_replace('/[^\d+]/', '', $this->input('phone')) : null,
            'rm' => $this->filled('rm') ? trim($this->input('rm')) : null,
        ]);
    }

    public function rules(): array
    {
        $patient = $this->route('patient');

        return [
            'name' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date', 'before_or_equal:today', 'after:1900-01-01'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'regex:/^\+?\d{8,15}$/', 'required_without:email'],
            'rm' => [
                'nullable', 'string', 'max:64',
                Rule::unique('patient_external_ids', 'value')
                    ->where('workspace_id', app(CurrentWorkspace::class)->id())
                    ->where('system', config('dental.default_rm_system'))
                    ->when($patient instanceof Patient, fn ($rule) => $rule->whereNot('patient_id', $patient->id)),
            ],
            'sex' => ['nullable', Rule::in(['L', 'P'])],
            'confirm_duplicate' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama', 'birth_date' => 'tanggal lahir', 'email' => 'email',
            'phone' => 'no. HP', 'rm' => 'No. RM', 'sex' => 'jenis kelamin',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required_without' => 'Isi email atau no. HP.',
            'phone.required_without' => 'Isi no. HP atau email.',
            'phone.regex' => 'No. HP 8–15 angka, boleh diawali +.',
            'rm.unique' => 'No. RM ini sudah dipakai pasien lain.',
        ];
    }

    /** @return array{name: string, birth_date: string, email: ?string, phone: ?string, sex: ?string} */
    public function patientData(): array
    {
        return $this->safe()->only(['name', 'birth_date', 'email', 'phone', 'sex']);
    }
}
