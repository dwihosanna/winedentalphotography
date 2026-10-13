<?php

namespace App\Http\Controllers;

use App\Actions\GenerateReport;
use App\Enums\ConsentType;
use App\Enums\Stage;
use App\Models\AuditLog;
use App\Models\OrthoCase;
use App\Models\Patient;
use App\Models\Photo;
use App\Models\PhotoSession;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Report before/after dan progres (FR-21, FR-26); hanya dokter (FR-23); anonimisasi butuh consent publikasi (FR-22). */
class ReportController extends Controller
{
    /** Pilih kasus dan dua sesi, lalu foto per slot; halaman ini sekaligus pratinjau isi report. */
    public function create(Request $request, Patient $patient): View
    {
        $this->authorize('report', $patient);

        $cases = $patient->cases()->with(['sessions' => fn ($q) => $q->orderBy('session_date')->orderBy('created_at')])->latest()->get();
        $case = $cases->firstWhere('id', $request->query('case'))
            ?? $cases->first(fn (OrthoCase $c) => $c->sessions->count() > 1)
            ?? $cases->first();
        $sessions = $case?->sessions ?? collect();

        $after = $sessions->firstWhere('id', $request->query('after')) ?? $sessions->last();
        $before = $sessions->firstWhere('id', $request->query('before'))
            ?? $sessions->first(fn (PhotoSession $s) => $s->stage === Stage::Initial && $s->isNot($after))
            ?? $sessions->first(fn (PhotoSession $s) => $s->isNot($after));

        $options = ['before' => $this->photoOptions($before), 'after' => $this->photoOptions($after)];
        $slots = collect(config('slots.slots'))
            ->filter(fn ($_, $code) => $options['before']->has($code) || $options['after']->has($code));

        return view('reports.create', [
            'patient' => $patient,
            'cases' => $cases,
            'case' => $case,
            'sessions' => $sessions,
            'before' => $before,
            'after' => $after,
            'slots' => $slots,
            'options' => $options,
            'canAnonymize' => $this->hasPublicationConsent($patient),
            'reports' => Report::whereIn('ortho_case_id', $cases->pluck('id'))->with('afterSession')->latest()->limit(20)->get(),
        ]);
    }

    public function store(Request $request, Patient $patient, GenerateReport $generate): RedirectResponse
    {
        $this->authorize('report', $patient);

        $data = $request->validate([
            'ortho_case_id' => ['required', 'string'],
            'before_session_id' => ['required', 'string'],
            'after_session_id' => ['required', 'string', 'different:before_session_id'],
            'slots' => ['required', 'array'],
            'slots.*.include' => ['nullable', 'boolean'],
            'slots.*.before' => ['nullable', 'string'],
            'slots.*.after' => ['nullable', 'string'],
            'anonymized' => ['nullable', 'boolean'],
        ], ['after_session_id.different' => 'Pilih dua sesi yang berbeda.']);

        $case = $patient->cases()->findOrFail($data['ortho_case_id']);
        $before = $case->sessions()->findOrFail($data['before_session_id']);
        $after = $case->sessions()->findOrFail($data['after_session_id']);

        $anonymized = (bool) ($data['anonymized'] ?? false);
        if ($anonymized && ! $this->hasPublicationConsent($patient)) {
            throw ValidationException::withMessages(['anonymized' => 'Report anonim untuk publikasi butuh consent publikasi pasien yang masih berlaku.']);
        }

        $selection = $this->selection($data['slots'], $before, $after);
        $report = $generate($case, $before, $after, $selection, $anonymized, $request->user()->id);

        return redirect()->route('reports.show', $report)->with('status', 'Report dibuat.');
    }

    public function show(Report $report): View
    {
        $patient = $report->orthoCase->patient;
        $this->authorize('report', $patient);

        return view('reports.show', ['report' => $report->load('beforeSession', 'afterSession', 'creator'), 'patient' => $patient]);
    }

    public function pdf(Report $report): StreamedResponse
    {
        $patient = $report->orthoCase->patient;
        $this->authorize('report', $patient);
        abort_if($report->pdf_path === null, 404);

        AuditLog::record('export', $report);
        $name = 'report-'.($report->anonymized ? 'anonim' : $patient->dpid).'-'.$report->created_at->format('Ymd').'.pdf';

        return Storage::disk($report->disk)->response($report->pdf_path, $name, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    /**
     * Foto per slot di satu sesi; foto yang berlaku dulu, lalu retake lama (dokter boleh memilih yang lebih baik).
     *
     * @return Collection<string, Collection<int, Photo>>
     */
    private function photoOptions(?PhotoSession $session): Collection
    {
        if ($session === null) {
            return collect();
        }

        return $session->photos()->where('mime', 'like', 'image/%')
            ->orderByRaw('replaced_by_photo_id is not null')->orderByDesc('created_at')
            ->get()->groupBy('slot_code');
    }

    /**
     * @param  array<string, array{include?: ?bool, before?: ?string, after?: ?string}>  $input
     * @return array<string, array{before: ?string, after: ?string}>
     */
    private function selection(array $input, PhotoSession $before, PhotoSession $after): array
    {
        $slots = config('slots.slots');
        $selection = [];

        foreach ($input as $code => $row) {
            if (! ($row['include'] ?? false) || ! isset($slots[$code])) {
                continue;
            }
            $pair = [];
            foreach (['before' => $before, 'after' => $after] as $side => $session) {
                $id = $row[$side] ?? null;
                $valid = $id === null || Photo::whereKey($id)->where('photo_session_id', $session->id)
                    ->where('slot_code', $code)->where('mime', 'like', 'image/%')->exists();
                if (! $valid) {
                    throw ValidationException::withMessages(['slots' => "Foto {$code} tidak cocok dengan sesi yang dipilih."]);
                }
                $pair[$side] = $id;
            }
            if ($pair['before'] !== null || $pair['after'] !== null) {
                $selection[$code] = $pair;
            }
        }

        if ($selection === []) {
            throw ValidationException::withMessages(['slots' => 'Pilih minimal satu slot berfoto.']);
        }

        return $selection;
    }

    private function hasPublicationConsent(Patient $patient): bool
    {
        return $patient->consents()->where('type', ConsentType::Publication)->get()->contains->isActive();
    }
}
