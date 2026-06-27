<?php

namespace App\Models\Concerns;

use App\Models\Scopes\OrganizationScope;
use App\Services\TenantContext;

trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function ($model) {
            if (! $model->organization_id) {
                $tenantContext = app(TenantContext::class);

                if ($tenantContext->hasOrganizationId()) {
                    $model->organization_id = $tenantContext->getOrganizationId();
                }
            }
        });
    }

    public function organization()
    {
        return $this->belongsTo(\App\Models\Organization::class);
    }
}
