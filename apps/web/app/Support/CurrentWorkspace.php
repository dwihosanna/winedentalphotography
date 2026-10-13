<?php

namespace App\Support;

use App\Enums\MemberRole;
use App\Models\Workspace;
use Closure;

/**
 * Workspace aktif untuk request/job ini. Didaftarkan sebagai scoped binding, jadi otomatis kosong lagi
 * di request atau job berikutnya. Semua query model BelongsToWorkspace dibatasi ke workspace ini.
 */
class CurrentWorkspace
{
    private ?Workspace $workspace = null;

    private ?MemberRole $role = null;

    public function set(?Workspace $workspace, ?MemberRole $role = null): void
    {
        $this->workspace = $workspace;
        $this->role = $workspace ? $role : null;
    }

    /** Peran pengguna yang login di workspace ini; null di luar request (mis. job). */
    public function role(): ?MemberRole
    {
        return $this->role;
    }

    public function isDentist(): bool
    {
        return $this->role === MemberRole::Dentist;
    }

    public function get(): ?Workspace
    {
        return $this->workspace;
    }

    public function id(): ?string
    {
        return $this->workspace?->getKey();
    }

    /** Jalankan $callback dengan workspace lain sebagai konteks, lalu kembalikan konteks sebelumnya. */
    public function run(Workspace $workspace, Closure $callback): mixed
    {
        $previous = $this->workspace;
        $this->workspace = $workspace;

        try {
            return $callback();
        } finally {
            $this->workspace = $previous;
        }
    }
}
