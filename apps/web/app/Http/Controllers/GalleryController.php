<?php

namespace App\Http\Controllers;

use App\Enums\Stage;
use App\Models\Patient;
use App\Models\Photo;
use App\Models\PhotoSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Galeri per kategori (FR-15), perbandingan side-by-side (FR-17), dan detail + metadata foto (FR-18). */
class GalleryController extends Controller
{
    public function index(Request $request, Patient $patient): View
    {
        $this->authorize('view', $patient);

        $slots = config('slots.slots');
        $filters = $request->validate([
            'slot' => ['nullable', Rule::in(array_keys($slots))],
            'stage' => ['nullable', Rule::enum(Stage::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $sessions = $this->sessions($patient)
            ->when($filters['stage'] ?? null, fn ($q, $stage) => $q->where('stage', $stage))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('session_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('session_date', '<=', $to))
            ->get()->keyBy('id');

        $photos = $this->currentPhotos($sessions)
            ->when($filters['slot'] ?? null, fn ($q, $slot) => $q->where('slot_code', $slot))
            ->get()
            ->each(fn (Photo $p) => $p->setRelation('session', $sessions[$p->photo_session_id]));

        // Urut kronologis di tiap kategori (kiri = paling awal), kategori mengikuti urutan slot.
        $groups = $this->chronological($photos)
            ->groupBy('slot_code')
            ->sortBy(fn ($_, $code) => array_search($code, array_keys($slots), true));

        return view('gallery.index', [
            'patient' => $patient,
            'groups' => $groups,
            'slots' => $slots,
            'filters' => $filters,
            'filtered' => array_filter($filters) !== [],
        ]);
    }

    /** Dua sesi mana pun untuk slot yang sama; default sesi paling awal vs paling akhir. */
    public function compare(Request $request, Patient $patient): View
    {
        $this->authorize('view', $patient);

        $slots = config('slots.slots');
        $data = $request->validate([
            'slot' => ['required', Rule::in(array_keys($slots))],
            'a' => ['nullable', 'string'],
            'b' => ['nullable', 'string'],
        ]);

        $sessions = $this->sessions($patient)->get()->keyBy('id');
        $photos = $this->chronological(
            $this->currentPhotos($sessions)->where('slot_code', $data['slot'])->get()
                ->each(fn (Photo $p) => $p->setRelation('session', $sessions[$p->photo_session_id]))
        )->keyBy('photo_session_id');

        $b = $photos->get($data['b'] ?? '') ?? $photos->last();
        $a = $photos->get($data['a'] ?? '') ?? $photos->first(fn (Photo $p) => $p !== $b) ?? $b;

        return view('gallery.compare', [
            'patient' => $patient,
            'slotCode' => $data['slot'],
            'slot' => $slots[$data['slot']],
            'slots' => $slots,
            'photos' => $photos,
            'a' => $a,
            'b' => $photos->count() > 1 ? $b : null,
        ]);
    }

    public function show(Photo $photo): View
    {
        $session = $photo->session;
        $patient = $session->orthoCase->patient;
        $this->authorize('view', $patient);

        return view('gallery.show', [
            'photo' => $photo->load('takenBy'),
            'session' => $session,
            'case' => $session->orthoCase,
            'patient' => $patient,
            'slot' => config("slots.slots.{$photo->slot_code}"),
            'versions' => $this->versions($photo),
        ]);
    }

    /** Foto ini ditambah foto lama yang (berantai) digantikannya; slot FREE berisi beberapa foto yang bukan retake. */
    private function versions(Photo $photo): int
    {
        $previous = Photo::where('photo_session_id', $photo->photo_session_id)->where('slot_code', $photo->slot_code)
            ->whereNotNull('replaced_by_photo_id')->pluck('replaced_by_photo_id', 'id');
        $chain = [$photo->id];
        while ($older = $previous->filter(fn ($by) => in_array($by, $chain, true))->keys()->diff($chain)->all()) {
            $chain = [...$chain, ...$older];
        }

        return count($chain);
    }

    /** @return Builder<PhotoSession> */
    private function sessions(Patient $patient): Builder
    {
        return PhotoSession::whereIn('ortho_case_id', $patient->cases()->select('id'));
    }

    /**
     * Foto yang berlaku (bukan yang sudah diganti retake) dari sesi-sesi ini.
     *
     * @return Builder<Photo>
     */
    private function currentPhotos(Collection $sessions): Builder
    {
        return Photo::whereIn('photo_session_id', $sessions->keys())->whereNull('replaced_by_photo_id');
    }

    private function chronological(Collection $photos): Collection
    {
        return $photos->sortBy(fn (Photo $p) => $p->session->session_date->format('Y-m-d')
            .$p->session->created_at->format('YmdHis').$p->created_at->format('YmdHis'))->values();
    }
}
