<?php

namespace App\Models\Concerns;

use App\Models\Scopes\WorkspaceScope;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Model data pasien: dibatasi ke workspace aktif saat dibaca dan otomatis diberi workspace_id saat dibuat. */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope(new WorkspaceScope);

        static::creating(function ($model) {
            $current = app(CurrentWorkspace::class)->id();

            if ($current === null) {
                throw new LogicException('Membuat '.$model::class.' tanpa workspace aktif.');
            }

            $model->workspace_id ??= $current;

            if ($model->workspace_id !== $current) {
                throw new LogicException('Membuat '.$model::class.' di workspace lain.');
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('workspace_id')) {
                throw new LogicException('workspace_id tidak boleh diubah.');
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
