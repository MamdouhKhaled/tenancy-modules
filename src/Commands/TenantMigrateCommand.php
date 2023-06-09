<?php

namespace Mamdouh\TenancyModules\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Arr;
use Nwidart\Modules\Migrations\Migrator;
use Nwidart\Modules\Module;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class TenantMigrateCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'module:tenant-migrate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate the migrations from the specified module or from all modules.';

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
     *
     * @return mixed
     */
    public function handle(): int
    {
        $this->configOverwrite();
        $this->module = $this->laravel['modules'];

        $name = $this->argument('module');
        if ($name) {
            $module = $this->module->findOrFail($name);

            $this->migrate($module);

            return 0;
        }

        foreach ($this->module->getOrdered($this->option('direction')) as $module) {
            $this->line('Running for module: <info>' . $module->getName() . '</info>');
            $this->migrate($module);
        }

        return 0;
    }

    /**
     * Run the migration from the specified module.
     *
     * @param Module $module
     */
    protected function migrate(Module $module)
    {
        $path = str_replace(base_path(), '', (new Migrator($module, $this->getLaravel()))->getPath());
        if ($this->option('subpath')) {
            $path = $path . "/" . $this->option("subpath");
        }
        tenancy()->runForMultiple($this->option('tenants'), function ($tenant) use($path,$module){
            $this->line("Tenant: {$tenant->getTenantKey()}");
            $this->call('migrate', [
                '--path' => $path,
                '--database' => $this->option('database'),
                '--pretend' => $this->option('pretend'),
                '--force' => $this->option('force'),
            ]);

            if ($this->option('seed')) {
                $this->call('module:seed', ['module' => $module->getName(), '--force' => $this->option('force')]);
            }
        });

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
            ['direction', 'd', InputOption::VALUE_OPTIONAL, 'The direction of ordering.', 'asc'],
            ['database', null, InputOption::VALUE_OPTIONAL, 'The database connection to use.'],
            ['pretend', null, InputOption::VALUE_NONE, 'Dump the SQL queries that would be run.'],
            ['force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production.'],
            ['seed', null, InputOption::VALUE_NONE, 'Indicates if the seed task should be re-run.'],
            ['subpath', null, InputOption::VALUE_OPTIONAL, 'Indicate a subpath to run your migrations from'],
            ['tenant', null, InputOption::VALUE_OPTIONAL, 'Run Migration rollback in specific tenant'],
        ];
    }
}
