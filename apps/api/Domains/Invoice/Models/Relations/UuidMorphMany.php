<?php

namespace Domains\Invoice\Models\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class UuidMorphMany extends MorphMany
{
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $parentKey = $this->getQualifiedParentKeyName();
        $foreignKey = $this->getExistenceCompareKey();

        return $query->select($columns)
            ->whereRaw("CAST({$parentKey} AS text) = {$foreignKey}")
            ->where($query->qualifyColumn($this->morphType), $this->morphClass);
    }
}
