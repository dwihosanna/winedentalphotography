<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menetapkan workspace aktif dari sesi login, hanya bila pengguna anggota aktif workspace itu.
 * Harus jalan sebelum route model binding (lihat bootstrap/app.php) agar binding ikut terbatas workspace:
 * ID pasien dari workspace lain menghasilkan 404, bukan data.
 */
class SetCurrentWorkspace
{
    public function __construct(private CurrentWorkspace $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_if($user === null || $user->isSuspended(), 403);

        $membership = ($id = $request->session()->get('workspace_id'))
            ? $user->activeMembership($id)
            : $user->memberships()->where('status', 'active')->oldest()->first();

        abort_if($membership === null, 403, 'Tidak ada workspace aktif.');

        $workspace = Workspace::findOrFail($membership->workspace_id);
        $request->session()->put('workspace_id', $workspace->id);

        // Workspace ditangguhkan = hanya baca; data tetap disimpan (FR-34).
        abort_if($workspace->isSuspended() && ! $request->isMethodSafe(), 403, 'Akun ditangguhkan: hanya baca.');

        $this->current->set($workspace, $membership->role);

        return $next($request);
    }
}
