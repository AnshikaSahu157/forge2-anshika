<?php

namespace App\Models\Concerns;

use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function ($model) {
            if (! $model->organization_id && session()->has('organization_id')) {
                $model->organization_id = session('organization_id');
            }
        });
    }

    public function organization()
    {
        return $this->belongsTo(\App\Models\Organization::class);
    }
}
