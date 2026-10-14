<?php

namespace App\Http\Controllers;

use App\Enums\Stage;
use App\Http\Requests\StoreSessionRequest;
use App\Models\AuditLog;
use App\Models\OrthoCase;
use App\Models\Patient;
use App\Models\PhotoSession;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Kasus + sesi foto (FR-04, FR-05, FR-09) dan Arsip pasien (FR-33). */
class PhotoSessionController extends Controller
{
    public function __construct(private CurrentWorkspace $current) {}

    public function index(Patient $patient): View
    {
        $this->authorize('view', $patient);

        $sessions = PhotoSession::whereIn('ortho_case_id', $patient->cases()->pluck('id'))
            ->withCount(['photos as photo_count' => fn ($q) => $q->whereNull('replaced_by_photo_id')])
            ->orderByDesc('session_date')->orderByDesc('created_at')
            ->get();

        return view('sessions.index', ['patient' => $patient, 'sessions' => $sessions]);
    }

    public function create(Patient $patient): View
    {
        $this->authorize('view', $patient);

        $cases = $patient->cases()->where('status', 'active')->withCount('sessions')->orderByDesc('created_at')->get();

        return view('sessions.create', [
            'patient' => $patient,
            'cases' => $cases,
            'canCreateCase' => $this->current->isDentist(),
        ]);
    }

    public function store(StoreSessionRequest $request, Patient $patient): RedirectResponse
    {
        $this->authorize('view', $patient);
        $data = $request->validated();

        $session = DB::transaction(function () use ($data, $patient, $request) {
            if ($request->isNewCase()) {
                $case = $patient->cases()->create($request->caseAttributes() + ['dentist_user_id' => $request->user()->id]);
                AuditLog::record('create', $case);
            } else {
                $case = OrthoCase::lockForUpdate()->findOrFail($data['ortho_case_id']);
            }

            $stage = Stage::from($data['stage']);
            $session = $case->sessions()->create([
                'stage' => $stage,
                'control_number' => $stage === Stage::Control ? $case->nextControlNumber() : null,
                'session_date' => $data['session_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            AuditLog::record('create', $session);

            return $session;
        });

        return redirect()->route('sessions.show', $session);
    }

    public function show(PhotoSession $session): View
    {
        $patient = $session->orthoCase->patient;
        $this->authorize('view', $patient);

        $photos = $session->currentPhotos()->get()->keyBy('slot_code');

        return view('sessions.show', [
            'session' => $session,
            'patient' => $patient,
            'slots' => $session->slots(),
            'photos' => $photos,
            'missing' => $session->missingRequiredSlots(),
        ]);
    }

    /** Keterangan dan tanggal sesi bisa diubah (FR-33); tahap tidak, karena menentukan slot. */
    public function update(Request $request, PhotoSession $session): RedirectResponse
    {
        $this->authorize('view', $session->orthoCase->patient);

        $data = $request->validate([
            'session_date' => ['required', 'date', 'before_or_equal:today', 'after:2000-01-01'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $session->fill($data);
        if ($session->isDirty()) {
            AuditLog::record('update', $session, ['fields' => array_keys($session->getDirty())]);
            $session->save();
        }

        return redirect()->route('sessions.show', $session)->with('status', 'Sesi disimpan.');
    }
}
