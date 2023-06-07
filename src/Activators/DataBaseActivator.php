<?php

namespace Mamdouh\TenancyModules\Activators;

use Mamdouh\TenancyModules\IdentificationTenant;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Module;
use Stancl\Tenancy\Middleware\IdentificationMiddleware;

class DataBaseActivator implements ActivatorInterface
{
    protected $tenant;
    protected $modulesStatuses;

    public function __construct()
    {
        $this->tenant = app(IdentificationTenant::class)->getTenant();
    }

    /**
     * @inheritDoc
     */
    public function enable(Module $module): void
    {
        // TODO: Implement enable() method.
    }

    /**
     * @inheritDoc
     */
    public function disable(Module $module): void
    {
        // TODO: Implement disable() method.
    }

    /**
     * @inheritDoc
     */
    public function hasStatus(Module $module, bool $status): bool
    {
        // Centeral
        // Tenant
        if($req = \Mamdouh\TenancyModules\Models\Module::where([
            'tenant_id' => $this->tenant->id,
            'name' => $module->getName()
        ])->first()){
            return $req->isActive;
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    public function setActive(Module $module, bool $active): void
    {
        // TODO: Implement setActive() method.
    }

    /**
     * @inheritDoc
     */
    public function setActiveByName(string $name, bool $active): void
    {
        // TODO: Implement setActiveByName() method.
    }

    /**
     * @inheritDoc
     */
    public function delete(Module $module): void
    {
        // TODO: Implement delete() method.
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        // TODO: Implement reset() method.
    }
}
