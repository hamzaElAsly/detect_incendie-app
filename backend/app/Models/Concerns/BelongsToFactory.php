<?php

namespace App\Models\Concerns;

use App\Models\Factory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToFactory
{
    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function scopeForFactory(Builder $q, Factory|string $factory): Builder
    {
        return $q->where($this->qualifyColumn('factory_id'), $factory instanceof Factory ? $factory->getKey() : $factory);
    }
}
