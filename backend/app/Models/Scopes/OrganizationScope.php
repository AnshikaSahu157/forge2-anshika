<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Session;

class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (Session::has('organization_id')) {
            $builder->where($model->getTable() . '.organization_id', Session::get('organization_id'));
        }
    }
}
