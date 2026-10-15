<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientRequest;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Dashboard dokter (FR-31, FR-01), tambah/ubah pasien (FR-02, FR-30) dan sheet pasien (FR-32). */
class PatientController extends Controller
{
    public function __construct(private CurrentWorkspace $current) {}

    /**
     * Semua pasien dirender sekaligus (1.000 baris tetap ringan) agar pencarian saat mengetik bisa
     * langsung di browser. ?q= adalah cadangan tanpa JavaScript.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Patient::class);

        $patients = Patient::with(['externalIds', 'avatarPhoto'])->orderBy('name')->get();
        if ($q = trim((string) $request->query('q'))) {
            $patients = $patients->filter(fn (Patient $p) => $this->matches($this->searchText($p), $q));
        }

        return view('patients.index', [
            'groups' => $patients->groupBy(fn (Patient $p) => $this->initial($p->name)),
            'searchText' => fn (Patient $p) => $this->searchText($p),
            'workspace' => $this->current->get(),
            'patientCount' => Patient::where('is_demo', false)->count(),
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Patient::class);

        return view('patients.create', ['patient' => new Patient, 'rm' => null]);
    }

    public function store(PatientRequest $request): RedirectResponse
    {
        if ($duplicates = $this->duplicateWarning($request)) {
            return $duplicates;
        }

        $patient = DB::transaction(function () use ($request) {
            // Kunci baris workspace agar dua penyimpanan bersamaan tidak melewati batas 50 pasien (FR-34).
            $workspace = Workspace::whereKey($this->current->id())->lockForUpdate()->first();
            if (! $workspace->canAddPatient()) {
                return null;
            }

            $patient = Patient::create($request->patientData());
            $this->syncRm($patient, $request->validated('rm'));
            AuditLog::record('create', $patient);

            return $patient;
        });

        if ($patient === null) {
            return back()->withInput()->withErrors([
                'quota' => 'Akun Free dibatasi 50 pasien. Ajukan Premium untuk menambah pasien tanpa batas.',
            ]);
        }

        return redirect()->route('patients.show', $patient)->with('status', 'Pasien disimpan.');
    }

    public function show(Patient $patient): View
    {
        $this->authorize('view', $patient);
        AuditLog::record('view', $patient);

        return view('patients.show', ['patient' => $patient->load('externalIds')]);
    }

    public function edit(Patient $patient): View
    {
        $this->authorize('update', $patient);

        return view('patients.edit', ['patient' => $patient, 'rm' => $this->rm($patient)]);
    }

    public function update(PatientRequest $request, Patient $patient): RedirectResponse
    {
        if ($duplicates = $this->duplicateWarning($request, $patient)) {
            return $duplicates;
        }

        DB::transaction(function () use ($request, $patient) {
            $patient->fill($request->patientData());
            $changed = array_keys($patient->getDirty());
            $patient->save();

            if ($this->rm($patient) !== $request->validated('rm')) {
                $this->syncRm($patient, $request->validated('rm'));
                $changed[] = 'rm';
            }

            // Hanya nama field yang dicatat, bukan nilainya, agar audit log tidak menyalin data pribadi.
            if ($changed) {
                AuditLog::record('update', $patient, ['fields' => $changed]);
            }
        });

        return redirect()->route('patients.show', $patient)->with('status', 'Perubahan disimpan.');
    }

    /** Nama + tanggal lahir sama di workspace ini → minta konfirmasi dulu (FR-02). */
    private function duplicateWarning(PatientRequest $request, ?Patient $except = null): ?RedirectResponse
    {
        if ($request->boolean('confirm_duplicate')) {
            return null;
        }

        $matches = Patient::where('name', $request->validated('name'))
            ->whereDate('birth_date', $request->validated('birth_date'))
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->get(['id', 'name', 'dpid']);

        return $matches->isEmpty() ? null : back()->withInput()->with('duplicates', $matches->map->only(['id', 'name', 'dpid'])->all());
    }

    private function rm(Patient $patient): ?string
    {
        return $patient->externalIds->firstWhere('system', config('dental.default_rm_system'))?->value;
    }

    private function syncRm(Patient $patient, ?string $value): void
    {
        $system = config('dental.default_rm_system');
        $patient->externalIds()->where('system', $system)->delete();

        if ($value !== null) {
            $patient->externalIds()->create(['system' => $system, 'value' => $value, 'source' => 'manual']);
        }
        $patient->unsetRelation('externalIds');
    }

    /** Teks pencarian: nama, DPID (tanpa strip), no. HP, No. RM; huruf kecil. */
    private function searchText(Patient $patient): string
    {
        return mb_strtolower(implode(' ', [
            $patient->name,
            str_replace('-', '', $patient->dpid),
            $patient->phone,
            ...$patient->externalIds->pluck('value'),
        ]));
    }

    /** Sama dengan pencarian di browser (resources/js/patients.js): cocok apa adanya, tanpa strip, atau tanpa spasi. */
    private function matches(string $text, string $q): bool
    {
        $q = mb_strtolower($q);
        foreach ([$q, str_replace('-', '', $q), preg_replace('/[\s-]/', '', $q)] as $variant) {
            if ($variant !== '' && str_contains($text, $variant)) {
                return true;
            }
        }

        return false;
    }

    private function initial(string $name): string
    {
        $c = mb_strtoupper(mb_substr(ltrim($name), 0, 1));

        return preg_match('/^[A-Z]$/', $c) ? $c : '#';
    }
}
