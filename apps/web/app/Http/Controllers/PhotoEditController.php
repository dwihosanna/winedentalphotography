<?php

namespace App\Http\Controllers;

use App\Actions\EditPhoto;
use App\Models\Photo;
use App\Support\PhotoEdit;
use App\Support\SlotGuides;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Edit non-destruktif: crop, putar, luruskan, balik (FR-13); auto-flip foto cermin bisa dimatikan (FR-14). */
class PhotoEditController extends Controller
{
    public function edit(Photo $photo): View
    {
        $patient = $photo->session->orthoCase->patient;
        $this->authorize('view', $patient);
        abort_unless($photo->isImage(), 404);

        return view('photos.edit', [
            'photo' => $photo,
            'patient' => $patient,
            'slot' => config("slots.slots.{$photo->slot_code}"),
            'config' => [
                'src' => $photo->url('original'),
                'params' => $photo->edit_params ?? PhotoEdit::normalize([]),
                'maxStraighten' => PhotoEdit::MAX_STRAIGHTEN,
                'mirrorAxis' => SlotGuides::mirror($photo->slot_code)['axis'] ?? null,
            ],
        ]);
    }

    public function update(Request $request, Photo $photo, EditPhoto $editPhoto): RedirectResponse
    {
        $this->authorize('view', $photo->session->orthoCase->patient);

        $data = $request->validate(['params' => ['required', 'json']]);
        $params = json_decode($data['params'], true);
        if (! is_array($params)) {
            return back()->withErrors(['params' => 'Parameter edit tidak valid.']);
        }

        $editPhoto($photo, $params);

        return redirect()->route('photos.show', $photo)->with('status', 'Edit disimpan. Foto asli tetap tersimpan.');
    }

    public function destroy(Photo $photo, EditPhoto $editPhoto): RedirectResponse
    {
        $this->authorize('view', $photo->session->orthoCase->patient);

        $editPhoto($photo, null);

        return redirect()->route('photos.show', $photo)->with('status', 'Edit direset ke foto asli.');
    }
}
