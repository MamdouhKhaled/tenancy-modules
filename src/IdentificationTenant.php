<?php

namespace Mamdouh\TenancyModules;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Database\Models\Tenant;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;

class IdentificationTenant
{
    private $tenant;

    public function __construct(Request $request)
    {
        $this->tenant = config('tenancy.tenant_model')::query()
            ->whereHas('domains', function (Builder $query) use ($request) {
                $query->where('domain', $request->getHost());
            })->first();
    }

    public function getTenant()
    {
        return $this->tenant->id;
    }
}
