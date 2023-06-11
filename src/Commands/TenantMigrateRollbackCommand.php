<?php

namespace Mamdouh\TenancyModules\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Arr;
use Nwidart\Modules\Migrations\Migrator;
use Nwidart\Modules\Traits\MigrationLoaderTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class TenantMigrateRollbackCommand extends Command
{
    use MigrationLoaderTrait;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'module:tenant-migrate-rollback';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback the modules migrations.';

    /**
     * @var \Nwidart\Modules\Contracts\RepositoryInterface
     */
    protected $module;

    private function configOverwrite() :void
    {
        $configRepository = app()->make(Repository::class);
        $config = Arr::dot($configRepository->get('tenancymodules'));
        foreach ($config as $configKey => $configValue){
            $configRepository->set($configKey,$configValue);
        }
    }
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->configOverwrite();
        $this->module = $this->laravel['modules'];
        $name = $this->argument('module');
        tenancy()->runForMultiple($this->option('tenants'), function ($tenant) use($name){
            $this->line("Tenant: {$tenant->getTenantKey()}");
            if (!empty($name)) {
                $this->rollback($name);

                return 0;
            }
            foreach ($this->module->getOrdered('Tenant') as $module) {
                $this->line('Running for module: <info>' . $module->getName() . '</info>');

                $this->rollback($module);
            }
        });

        return 0;
    }

    /**
     * Rollback migration from the specified module.
     *
     * @param $module
     */
    public function rollback($module)
    {
        if (is_string($module)) {
            $module = $this->module->findOrFail($module);
        }
        $migrator = new Migrator($module, $this->getLaravel());
        $database = $this->option('database');

        if (!empty($database)) {
            $migrator->setDatabase($database);
        }
        $migrated = $migrator->rollback();

        if (count($migrated)) {
            foreach ($migrated as $migration) {
                $this->line("Rollback: <info>{$migration}</info>");
            }

            return;
        }

        $this->comment('Nothing to rollback.');
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['module', InputArgument::OPTIONAL, 'The name of module will be used.'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['direction', 'd', InputOption::VALUE_OPTIONAL, 'The direction of ordering.', 'desc'],
            ['database', null, InputOption::VALUE_OPTIONAL, 'The database connection to use.'],
            ['force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production.'],
            ['pretend', null, InputOption::VALUE_NONE, 'Dump the SQL queries that would be run.'],
            ['tenants', null, InputOption::VALUE_NONE, 'Run Migration in specific tenant'],
        ];
    }
}
