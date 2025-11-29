<?php

namespace Mamdouh\TenancyModules\Commands;

use Illuminate\Console\Command;
use Nwidart\Modules\Module;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class TenantDisableCommand extends Command
{
    protected $name = 'module:tenant-disable';

    protected $description = 'Disable the specified module for a tenant.';

    public function handle(): int
    {
        $tenant = $this->option('tenant');

        if (! $tenant) {
            $this->components->error('Tenant identifier is required. Use --tenant option.');

            return 1;
        }

        $this->setTenantContext($tenant);

        $moduleName = $this->argument('module');

        if ($moduleName) {
            return $this->disableModule($moduleName);
        }

        return $this->disableAllModules();
    }

    protected function disableAllModules(): int
    {
        $this->components->info('Disabling all modules...');

        $modules = $this->laravel['modules']->all();
        $disabledCount = 0;

        foreach ($modules as $module) {
            if ($this->disableModule($module) === 0) {
                $disabledCount++;
            }
        }

        $this->components->info("Disabled {$disabledCount} module(s).");

        return 0;
    }

    protected function disableModule(string|Module $module): int
    {
        try {
            $moduleInstance = $module instanceof Module
                ? $module
                : $this->laravel['modules']->findOrFail($module);

            if ($moduleInstance->isEnabled()) {
                $moduleInstance->disable();
                $this->components->info("Module [{$moduleInstance}] disabled successfully.");
            } else {
                $this->components->warn("Module [{$moduleInstance}] is already disabled.");
            }

            return 0;
        } catch (\Exception $e) {
            $this->components->error("Failed to disable module: {$e->getMessage()}");

            return 1;
        }
    }

    protected function setTenantContext(string $tenant): void
    {
        $activatorClass = $this->getActivatorClass();
        $activatorClass::setTenantIdentifier(fn () => $tenant);
    }

    protected function getActivatorClass(): string
    {
        $config = config('tenancymodules.modules');
        $activator = $config['activator'];

        return $config['activators'][$activator]['class'];
    }

    protected function getArguments(): array
    {
        return [
            ['module', InputArgument::OPTIONAL, 'Module name. If omitted, all modules will be disabled.'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['tenant', null, InputOption::VALUE_REQUIRED, 'Tenant identifier (required)'],
        ];
    }
}
