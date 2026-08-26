<?php

namespace App\Support\Concerns;

use App\Support\Context\EntityContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToEntity
{
    public static function bootBelongsToEntity(): void
    {
        static::addGlobalScope('entity', function (Builder $query) {
            $context = app(EntityContext::class);

            if ($context->hasEntity()) {
                $query->where($query->getModel()->qualifyColumn('entity_id'), $context->entityId());
            }
        });

        static::creating(function (Model $model) {
            if ($model->getAttribute('entity_id') === null) {
                $model->setAttribute('entity_id', app(EntityContext::class)->entityId());
            }
        });
    }

    public function initializeBelongsToEntity(): void
    {
        $this->mergeFillable(['entity_id']);
    }
}
