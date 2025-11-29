<?php

namespace Mamdouh\TenancyModules\Traits;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

trait TenantModuleAware
{
    public static $defaultCallback;

    public static function setTenantIdentifier($callback = null)
    {
        if (is_null($callback)) {
            return static::tenantIdentifier();
        }

        if (! is_callable($callback) && ! $callback instanceof static) {
            throw new InvalidArgumentException('The given callback should be callable or an instance of '.static::class);
        }
        static::$defaultCallback = $callback;
    }

    private function tenantIdentifier(): ?string
    {
        if (app()->runningInConsole()) {
            return is_callable(static::$defaultCallback)
                ? call_user_func(static::$defaultCallback)
                : static::$defaultCallback;
        }

        return config('tenancy.tenant_model')::query()
            ->whereHas('domains', function (Builder $query) {
                $query->where('domain', \request()->getHost());
            })->value('id');
    }
}
