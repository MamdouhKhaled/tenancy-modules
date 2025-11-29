<?php

namespace Mamdouh\TenancyModules\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Arr;
use Nwidart\Modules\Contracts\RepositoryInterface;
use Nwidart\Modules\Migrations\Migrator;
use Nwidart\Modules\Module;
use Nwidart\Modules\Traits\MigrationLoaderTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

class TenantMigrateRollbackCommand extends Command
{
    use MigrationLoaderTrait;

    protected $name = 'module:tenant-migrate-rollback';

    protected $description = 'Rollback the modules migrations for specific tenants.';

    protected RepositoryInterface $moduleRepository;

    public function handle(): int
    {
        try {
            $this->overwriteConfigFromTenancyModules();
            $this->moduleRepository = $this->laravel['modules'];

            $moduleName = $this->argument('module');

            if ($moduleName) {
                return $this->rollbackModule($moduleName);
            }

            return $this->rollbackAllModules();
        } catch (Throwable $e) {
            $this->components->error("Rollback failed: {$e->getMessage()}");
            $this->error($e->getTraceAsString());

            return 1;
        }
    }

    protected function rollbackModule(string $moduleName): int
    {
        try {
            $module = $this->moduleRepository->findOrFail($moduleName);

            $this->line("Rolling back migrations for module: <info>{$module->getName()}</info>");
            $this->runModuleRollback($module);

            return 0;
        } catch (Throwable $e) {
            $this->components->error("Failed to rollback module '{$moduleName}': {$e->getMessage()}");

            return 1;
        }
    }

    protected function rollbackAllModules(): int
    {
        $direction = $this->option('direction');
        $modules = $this->moduleRepository->getOrdered($direction);
        $failedModules = [];

        foreach ($modules as $module) {
            $this->line("Rolling back migrations for module: <info>{$module->getName()}</info>");

            try {
                $this->runModuleRollback($module);
            } catch (Throwable $e) {
                $failedModules[] = $module->getName();
                $this->components->error("Failed to rollback module '{$module->getName()}': {$e->getMessage()}");

                if (! $this->option('force')) {
                    $this->components->warn('Use --force to continue on errors');

                    return 1;
                }
            }
        }

        if (! empty($failedModules)) {
            $this->components->warn('Some modules failed to rollback: '.implode(', ', $failedModules));

            return 1;
        }

        $this->components->info('All modules rolled back successfully.');

        return 0;
    }

    protected function runModuleRollback(Module $module): void
    {
        $tenants = $this->resolveTenants();

        if (empty($tenants)) {
            throw new \RuntimeException('No tenants found to rollback. Use --tenants option to specify tenants.');
        }

        $this->components->info('Rolling back for '.count($tenants).' tenant(s)');

        $failedTenants = [];

        tenancy()->runForMultiple($tenants, function ($tenant) use ($module, &$failedTenants) {
            try {
                $this->line("  → Tenant: <comment>{$tenant->getTenantKey()}</comment>");

                $this->rollbackMigrations($module);

            } catch (Throwable $e) {
                $failedTenants[] = $tenant->getTenantKey();
                $this->components->error("  ✗ Failed for tenant {$tenant->getTenantKey()}: {$e->getMessage()}");

                if (! $this->option('force')) {
                    throw $e;
                }
            }
        });

        if (! empty($failedTenants)) {
            $this->components->warn('Failed tenants: '.implode(', ', $failedTenants));

            if (! $this->option('force')) {
                throw new \RuntimeException('Rollback failed for some tenants.');
            }
        }
    }

    protected function rollbackMigrations(Module $module): void
    {
        $migrator = new Migrator($module, $this->getLaravel());

        if ($database = $this->option('database')) {
            $migrator->setDatabase($database);
        }

        $migrated = $migrator->rollback();

        if (count($migrated)) {
            foreach ($migrated as $migration) {
                $this->line("    Rolled back: <info>{$migration}</info>");
            }

            return;
        }

        $this->line('    <comment>Nothing to rollback</comment>');
    }

    protected function resolveTenants(): array
    {
        $tenantsOption = $this->option('tenants');

        if (! $tenantsOption) {
            return tenancy()->all();
        }

        $tenantIds = array_map('trim', explode(',', $tenantsOption));
        $tenants = [];

        foreach ($tenantIds as $tenantId) {
            $tenant = tenancy()->find($tenantId);

            if (! $tenant) {
                throw new \InvalidArgumentException("Tenant '{$tenantId}' not found.");
            }

            $tenants[] = $tenant;
        }

        return $tenants;
    }

    protected function overwriteConfigFromTenancyModules(): void
    {
        $config = app(Repository::class);
        $tenancyConfig = Arr::dot($config->get('tenancymodules', []));

        foreach ($tenancyConfig as $key => $value) {
            $config->set($key, $value);
        }
    }

    protected function getArguments(): array
    {
        return [
            ['module', InputArgument::OPTIONAL, 'The name of module to rollback. If omitted, all modules will be rolled back.'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['direction', 'd', InputOption::VALUE_OPTIONAL, 'The direction of ordering.', 'desc'],
            ['database', null, InputOption::VALUE_OPTIONAL, 'The database connection to use.'],
            ['force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production and continue on errors.'],
            ['pretend', null, InputOption::VALUE_NONE, 'Dump the SQL queries that would be run.'],
            ['tenants', null, InputOption::VALUE_OPTIONAL, 'Comma-separated tenant IDs. If omitted, all tenants will be used.'],
        ];
    }
}
