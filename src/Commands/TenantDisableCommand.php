<?php

namespace Mamdouh\TenancyModules\Commands;

use Illuminate\Console\Command;
use Nwidart\Modules\Module;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class TenantDisableCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'module:tenant-disable {--tenant}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Disable the specified module.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Disabling module ...');

        if ($name = $this->argument('module') ) {
            $this->disable($name);

            return 0;
        }

        $this->disableAll();

        return 0;
    }

    /**
     * disableAll
     *
     * @return void
     */
    public function disableAll()
    {
        /** @var Modules $modules */
        $modules = $this->laravel['modules']->all();
        foreach ($modules as $module) {
            $this->disable($module);
        }
    }

    /**
     * disable
     *
     * @param string $name
     * @return void
     */
    public function disable($name)
    {
        if ($name instanceof Module) {
            $module = $name;
        }else {
            $module = $this->laravel['modules']->findOrFail($name);
        }
        $this->config()::setTenant($this->option('tenant'));
        if ($module->isEnabled()) {
            $module->disable();

            $this->components->info("Module [{$module}] disabled successful.");
        } else {
            $this->components->warn("Module [{$module}] has already disabled.");
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
            ['tenant', null, InputOption::VALUE_OPTIONAL, 'Disable Module To specific tenant'],
        ];
    }

    protected function config(){
        $config = app('config');
        return $config->get('tenancymodules.modules.activators.'.$config->get('tenancymodules.modules.activator').'.class');
    }
}
