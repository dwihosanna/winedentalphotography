<?php

namespace App\Http\Controllers;

use App\Actions\DeletePhotos;
use App\Actions\StorePhoto;
use App\Models\AuditLog;
use App\Models\Photo;
use App\Models\PhotoSession;
use App\Support\ReleaseDatabase;
use App\Support\SlotGuides;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Kamera (FR-06), unggah per slot dan banyak foto (FR-07, FR-08), dan akses file foto. */
class PhotoController extends Controller
{
    public function camera(PhotoSession $session, Request $request): View
    {
        $this->authorize('view', $session->orthoCase->patient);

        $guides = collect(SlotGuides::all()['slots'])->keyBy('code');
        $filled = $session->currentPhotos()->pluck('slot_code')->all();

        // Kamera hanya untuk slot foto; rontgen hanya diunggah (FR-08).
        $slots = $session->slots()->where('kind', 'photo')->values()->map(fn ($slot) => [
            'code' => $slot->slot_code,
            'label' => $slot->label,
            'required' => $slot->required,
            'filled' => in_array($slot->slot_code, $filled, true),
        ] + collect($guides->get($slot->slot_code, []))->only(['orientation', 'level', 'mirror', 'checklist', 'overlay'])->all());

        $start = $request->query('slot');
        if (! $slots->contains('code', $start)) {
            $start = $slots->first(fn ($s) => $s['required'] && ! $s['filled'])['code'] ?? $slots->first()['code'];
        }

        return view('sessions.camera', [
            'session' => $session,
            'patient' => $session->orthoCase->patient,
            'config' => [
                'slots' => $slots,
                'start' => $start,
                'overlayVersion' => SlotGuides::version(),
                'uploadUrl' => route('photos.store', $session),
                'photosUrl' => route('photos.index', $session),
                'doneUrl' => route('sessions.show', $session),
            ],
        ]);
    }

    /** Foto sesi ini untuk galeri di layar kamera (URL bertanda tangan berumur pendek). */
    public function index(PhotoSession $session): JsonResponse
    {
        $this->authorize('view', $session->orthoCase->patient);

        $slots = $session->slots()->pluck('label', 'slot_code');
        $order = $slots->keys()->flip();
        $photos = $session->currentPhotos()->where('mime', 'like', 'image/%')->get()
            ->sortBy(fn (Photo $p) => [$order[$p->slot_code] ?? 99, $p->created_at])
            ->values()
            ->map(fn (Photo $p) => [
                'id' => $p->id,
                'slot' => $p->slot_code,
                'label' => $slots[$p->slot_code] ?? $p->slot_code,
                'thumb' => $p->thumb_path ? $p->url('thumb') : $p->url($p->displayVariant()),
                'full' => $p->url($p->displayVariant()),
                'time' => $p->timestampText(),
            ]);

        return response()->json(['photos' => $photos]);
    }

    public function upload(PhotoSession $session): View
    {
        $this->authorize('view', $session->orthoCase->patient);

        $filled = $session->currentPhotos()->pluck('slot_code')->all();

        return view('sessions.upload', [
            'session' => $session,
            'patient' => $session->orthoCase->patient,
            'slots' => $session->slots()->map(fn ($s) => [
                'code' => $s->slot_code, 'label' => $s->label, 'required' => $s->required,
                'kind' => $s->kind, 'filled' => in_array($s->slot_code, $filled, true),
            ])->values(),
        ]);
    }

    public function store(Request $request, PhotoSession $session, StorePhoto $storePhoto): JsonResponse|RedirectResponse
    {
        $this->authorize('view', $session->orthoCase->patient);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.config('dental.max_upload_kb')],
            'slot_code' => ['required', 'string', 'max:8'],
            'source' => ['required', 'in:camera,upload'],
            'lens_mode' => ['nullable', 'in:main,multi,manual'],
            'zoom' => ['nullable', 'numeric', 'between:0,20'],
            'device' => ['nullable', 'string', 'max:255'],
        ], ['file.max' => 'File terlalu besar (maks. '.(config('dental.max_upload_kb') / 1024).' MB).']);

        $overlay = $data['source'] === 'camera' ? SlotGuides::version() : null;

        $photo = $storePhoto($session, $data['slot_code'], $request->file('file'), [
            'source' => $data['source'],
            'lens_mode' => $data['lens_mode'] ?? null,
            'zoom' => isset($data['zoom']) ? (float) $data['zoom'] : null,
            'device' => $data['device'] ?? null,
            'overlay_version' => $overlay,
        ], $request->user()->id);

        $session->refresh();
        if (! $request->expectsJson()) {
            return redirect()->route('sessions.show', $session)->with('status', "Foto {$photo->slot_code} disimpan.");
        }

        return response()->json([
            'id' => $photo->id,
            'slot_code' => $photo->slot_code,
            'thumb_url' => $photo->thumb_path ? $photo->url('thumb') : null,
            'width' => $photo->width,
            'height' => $photo->height,
            'exif_date_mismatch' => $photo->exifDateMismatch(),
            'session_status' => $session->status,
            'missing' => $session->missingRequiredSlots(),
        ], 201);
    }

    /** Hapus foto satu slot (beserta riwayat retake-nya); slot kembali kosong. */
    public function destroy(Photo $photo, DeletePhotos $deletePhotos): RedirectResponse
    {
        $session = $photo->session;
        $this->authorize('view', $session->orthoCase->patient);

        $deletePhotos($session, [$photo->slot_code]);

        return redirect()->route('sessions.show', $session)->with('status', "Foto {$photo->slot_code} dihapus.");
    }

    /** Hapus semua foto sesi; sesinya tetap ada (hapus sesi lewat halaman Ubah sesi). */
    public function destroyAll(PhotoSession $session, DeletePhotos $deletePhotos): RedirectResponse
    {
        $this->authorize('view', $session->orthoCase->patient);

        $count = $deletePhotos($session);

        return redirect()->route('sessions.show', $session)->with('status', "Foto di {$count} slot dihapus.");
    }

    /**
     * Foto ukuran penuh di dalam aplikasi, dengan tombol kembali dan Bagikan.
     * PWA iPhone membuka tautan target=_blank menutupi aplikasi tanpa jalan kembali.
     */
    public function image(Request $request, Photo $photo): View
    {
        $patient = $photo->session->orthoCase->patient;
        $this->authorize('view', $patient);
        abort_unless($photo->isImage(), 404);

        $variant = $request->query('versi') === 'asli' || $photo->displayVariant() === 'original' ? 'original' : 'edited';

        return view('photos.viewer', [
            'photo' => $photo,
            'patient' => $patient,
            'slot' => config("slots.slots.{$photo->slot_code}.label", $photo->slot_code),
            'variant' => $variant,
            'url' => $photo->url($variant),
            'fileName' => $photo->shareFileName($patient, $variant),
            'backUrl' => route('photos.show', $photo),
        ]);
    }

    /** Rontgen PDF di penampil dalam aplikasi (PWA iPhone tidak bisa membuka PDF di tab baru). */
    public function viewer(Photo $photo): View
    {
        $this->authorize('view', $photo->session->orthoCase->patient);
        abort_unless($photo->mime === 'application/pdf', 404);

        $slot = config("slots.slots.{$photo->slot_code}.label", $photo->slot_code);

        return view('pdf.viewer', [
            'title' => $slot,
            'pdfUrl' => $photo->url('original'),
            'fileName' => "{$photo->slot_code}-{$photo->session->session_date->format('Ymd')}.pdf",
            'backUrl' => route('photos.show', $photo),
        ]);
    }

    /** File lewat URL bertanda tangan + login + workspace; foto workspace lain = 404 dari route binding. */
    public function file(Photo $photo, string $variant): StreamedResponse
    {
        $this->authorize('view', $photo->session->orthoCase->patient);

        $path = match ($variant) {
            'thumb' => $photo->thumb_path ?? abort(404),
            'original' => $photo->original_path,
            'edited' => $photo->edited_path ?? abort(404),
            default => abort(404),
        };
        if ($variant !== 'thumb') {
            AuditLog::record('view', $photo, ['variant' => $variant]); // thumbnail galeri tidak dicatat agar log tidak membanjir
        }

        $disk = $photo->disk;
        // Koneksi MySQL dilepas sebelum file dikirim: hosting membatasi koneksi bersamaan, dan galeri memuat
        // banyak thumbnail sekaligus. Sesi login disimpan di file, jadi sisa request tidak butuh database.
        ReleaseDatabase::now();

        return Storage::disk($disk)->response($path, null, [
            'Content-Type' => $variant === 'original' ? $photo->mime : 'image/jpeg',
            'Cache-Control' => 'private, max-age='.(config('dental.signed_url_minutes') * 60),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
