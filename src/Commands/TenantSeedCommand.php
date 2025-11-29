<?php

namespace Mamdouh\TenancyModules\Commands;

use ErrorException;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Nwidart\Modules\Contracts\RepositoryInterface;
use Nwidart\Modules\Module;
use Nwidart\Modules\Support\Config\GenerateConfigReader;
use Nwidart\Modules\Traits\ModuleCommandTrait;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

class TenantSeedCommand extends Command
{
    use ModuleCommandTrait;

    protected $name = 'module:tenant-seed';

    protected $description = 'Run database seeder from the specified module or from all modules for specific tenants.';

    public function handle(): int
    {
        try {
            $this->overwriteConfigFromTenancyModules();

            $moduleName = $this->argument('module');

            if ($moduleName) {
                return $this->seedModule($moduleName);
            }

            return $this->seedAllModules();
        } catch (ErrorException $e) {
            $this->reportException($e);
            $this->renderException($this->getOutput(), $e);

            return 1;
        } catch (Throwable $e) {
            $this->reportException($e);
            $this->renderException($this->getOutput(), $e);

            return 1;
        }
    }

    protected function seedModule(string $moduleName): int
    {
        try {
            $module = $this->getModuleByName(Str::studly($moduleName));

            $this->line("Seeding module: <info>{$module->getName()}</info>");
            $this->runModuleSeed($module);

            return 0;
        } catch (Throwable $e) {
            $this->components->error("Failed to seed module '{$moduleName}': {$e->getMessage()}");

            return 1;
        }
    }

    protected function seedAllModules(): int
    {
        $modules = $this->getModuleRepository()->getOrdered();
        $failedModules = [];

        foreach ($modules as $module) {
            $this->line("Seeding module: <info>{$module->getName()}</info>");

            try {
                $this->runModuleSeed($module);
            } catch (Throwable $e) {
                $failedModules[] = $module->getName();
                $this->components->error("Failed to seed module '{$module->getName()}': {$e->getMessage()}");

                if (! $this->option('force')) {
                    $this->components->warn('Use --force to continue on errors');

                    return 1;
                }
            }
        }

        if (! empty($failedModules)) {
            $this->components->warn('Some modules failed to seed: '.implode(', ', $failedModules));

            return 1;
        }

        $this->components->info('All modules seeded successfully.');

        return 0;
    }

    protected function runModuleSeed(Module $module): void
    {
        $tenants = $this->resolveTenants();

        if (empty($tenants)) {
            throw new RuntimeException('No tenants found to seed. Use --tenants option to specify tenants.');
        }

        $this->components->info('Seeding for '.count($tenants).' tenant(s)');

        $failedTenants = [];

        tenancy()->runForMultiple($tenants, function ($tenant) use ($module, &$failedTenants) {
            try {
                $this->line("  → Tenant: <comment>{$tenant->getTenantKey()}</comment>");

                $this->seedModuleForTenant($module);

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
                throw new RuntimeException('Seeding failed for some tenants.');
            }
        }
    }

    protected function seedModuleForTenant(Module $module): void
    {
        $seeders = $this->getModuleSeeders($module);

        if (empty($seeders)) {
            $this->line('    <comment>No seeders found</comment>');

            return;
        }

        foreach ($seeders as $seeder) {
            $this->executeSeed($seeder);
        }

        $this->line('    <info>Seeded successfully</info>');
    }

    protected function getModuleSeeders(Module $module): array
    {
        $seeders = [];
        $name = $module->getName();
        $config = $module->get('migration');

        if (is_array($config) && array_key_exists('seeds', $config)) {
            foreach ((array) $config['seeds'] as $class) {
                if (class_exists($class)) {
                    $seeders[] = $class;
                }
            }
        } else {
            // Legacy support - try default seeder name
            $class = $this->getSeederName($name);
            if (class_exists($class)) {
                $seeders[] = $class;
            } else {
                // Look at other namespaces
                $classes = $this->getSeederNames($name);
                foreach ($classes as $class) {
                    if (class_exists($class)) {
                        $seeders[] = $class;
                    }
                }
            }
        }

        return $seeders;
    }

    protected function executeSeed(string $className): void
    {
        $params = [];

        if ($option = $this->option('class')) {
            $params['--class'] = Str::finish(
                substr($className, 0, strrpos($className, '\\')),
                '\\'
            ).$option;
        } else {
            $params['--class'] = $className;
        }

        if ($database = $this->option('database')) {
            $params['--database'] = $database;
        }

        if ($this->option('force')) {
            $params['--force'] = true;
        }

        $this->call('db:seed', $params);
    }

    protected function getSeederName(string $name): string
    {
        $name = Str::studly($name);
        $namespace = $this->laravel['modules']->config('namespace');
        $config = GenerateConfigReader::read('seeder');
        $seederPath = str_replace('/', '\\', $config->getPath());

        return $namespace.'\\'.$name.'\\'.$seederPath.'\\'.$name.'TenantDatabaseSeeder';
    }

    protected function getSeederNames(string $name): array
    {
        $name = Str::studly($name);
        $seederPath = GenerateConfigReader::read('seeder');
        $seederPath = str_replace('/', '\\', $seederPath->getPath());

        $foundModules = [];
        foreach ($this->laravel['modules']->config('scan.paths') as $path) {
            $namespace = array_slice(explode('/', $path), -1)[0];
            $foundModules[] = $namespace.'\\'.$name.'\\'.$seederPath.'\\'.$name.'TenantDatabaseSeeder';
        }

        return $foundModules;
    }

    protected function getModuleByName(string $name): Module
    {
        $modules = $this->getModuleRepository();

        if (! $modules->has($name)) {
            throw new RuntimeException("Module [{$name}] does not exist.");
        }

        return $modules->find($name);
    }

    protected function getModuleRepository(): RepositoryInterface
    {
        $modules = $this->laravel['modules'];

        if (! $modules instanceof RepositoryInterface) {
            throw new RuntimeException('Module repository not found!');
        }

        return $modules;
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

    protected function renderException($output, \Exception $e): void
    {
        $this->laravel[ExceptionHandler::class]->renderForConsole($output, $e);
    }

    protected function reportException(\Exception $e): void
    {
        $this->laravel[ExceptionHandler::class]->report($e);
    }

    protected function getArguments(): array
    {
        return [
            ['module', InputArgument::OPTIONAL, 'The name of module to seed. If omitted, all modules will be seeded.'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['class', null, InputOption::VALUE_OPTIONAL, 'The class name of the root seeder.'],
            ['database', null, InputOption::VALUE_OPTIONAL, 'The database connection to seed.'],
            ['force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production and continue on errors.'],
            ['tenants', null, InputOption::VALUE_OPTIONAL, 'Comma-separated tenant IDs. If omitted, all tenants will be used.'],
        ];
    }
}
