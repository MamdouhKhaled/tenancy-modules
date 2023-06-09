<?php

namespace Mamdouh\TenancyModules\Commands;

use Illuminate\Console\Command;
use Nwidart\Modules\Module;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class TenantEnableCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'module:tenant-enable {--tenant}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enable the specified module.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {

        $this->components->info('Enabling module ...');

            if ($name = $this->argument('module') ) {
                $this->enable($name);

                return 0;
            }

            $this->enableAll();
        return 0;
    }

    /**
     * enableAll
     *
     * @return void
     */
    public function enableAll()
    {
        /** @var Modules $modules */
        $modules = $this->laravel['modules']->all();
        foreach ($modules as $module) {

            $this->enable($module);
        }
    }

    /**
     * enable
     *
     * @param string $name
     * @return void
     */
    public function enable($name)
    {
        if ($name instanceof Module) {
            $module = $name;
        }else {
            $module = $this->laravel['modules']->findOrFail($name);
        }
        $this->config()::setTenant($this->option('tenant'));

        if ($module->isDisabled()) {
            $module->enable();

            $this->components->info("Module [{$module}] enabled successful.");
        }else {
            $this->components->warn("Module [{$module}] has already enabled.");
        }

    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['module', InputArgument::OPTIONAL, 'Module name.'],
        ];
    }

    protected function getOptions()
    {
        return [
            ['tenant', null, InputOption::VALUE_OPTIONAL, 'Enable Module To specific tenant'],
        ];
    }

    protected function config(){
        $config = app('config');
        return $config->get('tenancymodules.modules.activators.'.$config->get('tenancymodules.modules.activator').'.class');
    }
}
