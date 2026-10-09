<?php

namespace App\Observers;

use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;

class AuditableObserver
{
    public function created(Model $model): void
    {
        AuditLogger::record($model, 'created', newValues: $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        $oldValues = collect(array_keys($changes))
            ->mapWithKeys(fn (string $key): array => [$key => $model->getRawOriginal($key)])
            ->all();

        AuditLogger::record($model, 'updated', $oldValues, $changes);
    }

    public function deleted(Model $model): void
    {
        AuditLogger::record($model, 'deleted', oldValues: $model->getAttributes());
    }
}
