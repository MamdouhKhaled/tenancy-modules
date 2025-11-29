<?php

namespace Mamdouh\TenancyModules\Commands;

use Illuminate\Console\Command;
use Nwidart\Modules\Module;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class TenantEnableCommand extends Command
{
    protected $name = 'module:tenant-enable';

    protected $description = 'Enable the specified module for a tenant.';

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
            return $this->enableModule($moduleName);
        }

        return $this->enableAllModules();
    }

    protected function enableAllModules(): int
    {
        $this->components->info('Enabling all modules...');

        $modules = $this->laravel['modules']->all();
        $enabledCount = 0;

        foreach ($modules as $module) {
            if ($this->enableModule($module) === 0) {
                $enabledCount++;
            }
        }

        $this->components->info("Enabled {$enabledCount} module(s).");

        return 0;
    }

    protected function enableModule(string|Module $module): int
    {
        try {
            $moduleInstance = $module instanceof Module
                ? $module
                : $this->laravel['modules']->findOrFail($module);

            if ($moduleInstance->isDisabled()) {
                $moduleInstance->enable();
                $this->components->info("Module [{$moduleInstance}] enabled successfully.");
            } else {
                $this->components->warn("Module [{$moduleInstance}] is already enabled.");
            }

            return 0;
        } catch (\Exception $e) {
            $this->components->error("Failed to enable module: {$e->getMessage()}");

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
            ['module', InputArgument::OPTIONAL, 'Module name. If omitted, all modules will be enabled.'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['tenant', null, InputOption::VALUE_REQUIRED, 'Tenant identifier (required)'],
        ];
    }
}
