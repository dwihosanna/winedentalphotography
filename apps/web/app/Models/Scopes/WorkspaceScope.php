<?php

namespace App\Models\Scopes;

use App\Support\CurrentWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use LogicException;

/**
 * Pengganti row-level security: setiap query dibatasi ke workspace aktif.
 * Tanpa workspace aktif, query ditolak (fail closed) agar lupa set konteks tidak membocorkan data.
 * Akses lintas workspace (mis. admin platform) harus eksplisit: withoutGlobalScope(WorkspaceScope::class).
 */
class WorkspaceScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $workspaceId = app(CurrentWorkspace::class)->id();

        if ($workspaceId === null) {
            throw new LogicException('Query '.$model::class.' tanpa workspace aktif.');
        }

        $builder->where($model->qualifyColumn('workspace_id'), $workspaceId);
    }
}
