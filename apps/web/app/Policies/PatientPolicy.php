<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;
use App\Support\CurrentWorkspace;

/**
 * Pasien sudah dibatasi ke workspace aktif oleh WorkspaceScope; policy hanya mengatur peran.
 * SRS Bagian 4: dokter membuat dan mengubah pasien; asisten melihat dan memotret.
 */
class PatientPolicy
{
    public function __construct(private CurrentWorkspace $current) {}

    public function viewAny(User $user): bool
    {
        return $this->current->role() !== null;
    }

    public function view(User $user, Patient $patient): bool
    {
        return $patient->workspace_id === $this->current->id();
    }

    public function create(User $user): bool
    {
        return $this->current->isDentist();
    }

    public function update(User $user, Patient $patient): bool
    {
        return $this->view($user, $patient) && $this->current->isDentist();
    }
}
