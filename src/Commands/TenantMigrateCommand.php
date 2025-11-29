<?php

namespace Mamdouh\TenancyModules\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Arr;
use Nwidart\Modules\Contracts\RepositoryInterface;
use Nwidart\Modules\Migrations\Migrator;
use Nwidart\Modules\Module;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

class TenantMigrateCommand extends Command
{
    protected $name = 'module:tenant-migrate';

    protected $description = 'Migrate the migrations from the specified module or from all modules for specific tenants.';

    protected RepositoryInterface $moduleRepository;

    public function handle(): int
    {
        try {
            $this->overwriteConfigFromTenancyModules();
            $this->moduleRepository = $this->laravel['modules'];

            $moduleName = $this->argument('module');

            if ($moduleName) {
                return $this->migrateModule($moduleName);
            }

            return $this->migrateAllModules();
        } catch (Throwable $e) {
            $this->components->error("Migration failed: {$e->getMessage()}");
            $this->error($e->getTraceAsString());

            return 1;
        }
    }

    protected function migrateModule(string $moduleName): int
    {
        try {
            $module = $this->moduleRepository->findOrFail($moduleName);

            $this->line("Running migrations for module: <info>{$module->getName()}</info>");
            $this->runModuleMigration($module);

            return 0;
        } catch (Throwable $e) {
            $this->components->error("Failed to migrate module '{$moduleName}': {$e->getMessage()}");

            return 1;
        }
    }

    protected function migrateAllModules(): int
    {
        $direction = $this->option('direction');
        $modules = $this->moduleRepository->getOrdered($direction);
        $failedModules = [];

        foreach ($modules as $module) {
            $this->line("Running migrations for module: <info>{$module->getName()}</info>");

            try {
                $this->runModuleMigration($module);
            } catch (Throwable $e) {
                $failedModules[] = $module->getName();
                $this->components->error("Failed to migrate module '{$module->getName()}': {$e->getMessage()}");

                if (! $this->option('force')) {
                    $this->components->warn('Use --force to continue on errors');

                    return 1;
                }
            }
        }

        if (! empty($failedModules)) {
            $this->components->warn('Some modules failed to migrate: '.implode(', ', $failedModules));

            return 1;
        }

        $this->components->info('All modules migrated successfully.');

        return 0;
    }

    protected function runModuleMigration(Module $module): void
    {
        $migrator = new Migrator($module, $this->getLaravel());
        $path = str_replace(base_path(), '', $migrator->getPath());

        if ($subpath = $this->option('subpath')) {
            $path = rtrim($path, '/').'/'.ltrim($subpath, '/');
        }

        $tenants = $this->resolveTenants();

        if (empty($tenants)) {
            throw new \RuntimeException('No tenants found to migrate. Use --tenants option to specify tenants.');
        }

        $this->components->info('Migrating for '.count($tenants).' tenant(s)');

        $failedTenants = [];

        tenancy()->runForMultiple($tenants, function ($tenant) use ($path, $module, &$failedTenants) {
            try {
                $this->line("  → Tenant: <comment>{$tenant->getTenantKey()}</comment>");

                $this->call('migrate', [
                    '--path' => $path,
                    '--database' => $this->option('database'),
                    '--pretend' => $this->option('pretend'),
                    '--force' => $this->option('force'),
                ]);

                if ($this->option('seed')) {
                    $this->call('module:seed', [
                        'module' => $module->getName(),
                        '--force' => $this->option('force'),
                    ]);
                }

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
                throw new \RuntimeException('Migration failed for some tenants.');
            }
        }
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
            ['module', InputArgument::OPTIONAL, 'The name of module to migrate. If omitted, all modules will be migrated.'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['direction', 'd', InputOption::VALUE_OPTIONAL, 'The direction of ordering.', 'asc'],
            ['database', null, InputOption::VALUE_OPTIONAL, 'The database connection to use.'],
            ['pretend', null, InputOption::VALUE_NONE, 'Dump the SQL queries that would be run.'],
            ['force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production and continue on errors.'],
            ['seed', null, InputOption::VALUE_NONE, 'Indicates if the seed task should be re-run.'],
            ['subpath', null, InputOption::VALUE_OPTIONAL, 'Indicate a subpath to run your migrations from.'],
            ['tenants', null, InputOption::VALUE_OPTIONAL, 'Comma-separated tenant IDs. If omitted, all tenants will be used.'],
        ];
    }
}
