<?php

namespace Mamdouh\TenancyModules\Activators;

use App\Models\Module as BaseModule;
use Illuminate\Support\Facades\Schema;
use Mamdouh\TenancyModules\Traits\TenantModuleAware;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Module;

class DatabaseActivator implements ActivatorInterface
{
    use TenantModuleAware;

    public function enable(Module $module): void
    {
        $this->setActiveByName($module->getName(), true);
    }

    public function disable(Module $module): void
    {
        $this->setActiveByName($module->getName(), false);
    }

    public function hasStatus(Module|string $module, bool $status): bool
    {
        $name = $module instanceof Module ? $module->getName() : $module;
        // Check if table exists before querying
        if (! Schema::hasTable('base_modules')) {
            return false;
        }
        $currentStatus = BaseModule::where([
            'name' => $name,
            'tenant_id' => $this->tenantIdentifier(),
        ])->value('is_active');

        return $currentStatus === $status;
    }

    public function setActive(Module $module, bool $active): void
    {
        $this->setActiveByName($module->getName(), $active);
    }

    public function setActiveByName(string $name, bool $active): void
    {
        BaseModule::where([
            'name' => $name,
            'tenant_id' => $this->tenantIdentifier(),
        ])->update(['is_active' => $active]);
    }

    public function delete(Module $module): void
    {
        BaseModule::where([
            'name' => $module->getName(),
            'tenant_id' => $this->tenantIdentifier(),
        ])->delete();
    }

    public function reset(): void
    {
        BaseModule::where([
            'tenant_id' => $this->tenantIdentifier(),
        ])->delete();
    }
}
