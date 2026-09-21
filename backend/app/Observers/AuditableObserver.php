<?php

namespace App\Observers;

use App\Events\ModelAudited;
use Illuminate\Database\Eloquent\Model;

class AuditableObserver
{
    public function created(Model $model): void
    {
        event(new ModelAudited($model, 'created', null, $model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        event(new ModelAudited($model, 'updated', $model->getOriginal(), $model->getChanges()));
    }

    public function deleted(Model $model): void
    {
        event(new ModelAudited($model, 'deleted', $model->getOriginal(), null));
    }
}
