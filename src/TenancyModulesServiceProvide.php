<?php

namespace Mamdouh\TenancyModules;

use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use JetBrains\PhpStorm\NoReturn;
use Mamdouh\TenancyModules\Commands\TenantMigrateCommand;
use Mamdouh\TenancyModules\Commands\TenantMigrateRollbackCommand;
use Illuminate\Contracts\Config\Repository;

class TenancyModulesServiceProvide extends ServiceProvider
{
    private Repository $config;
    public function __construct($app)
    {

        //
//        private Repository $config
        parent::__construct($app);

    }

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tenancymodules.php','tenancymodules');
        $this->configOverwrite();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {

        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        if ($this->app->runningInConsole()) {
            $this->commands([
                TenantMigrateCommand::class,
                TenantMigrateRollbackCommand::class,
            ]);
        }
    }

    private function configOverwrite() :void
    {
        $this->config = $this->app->make(Repository::class);
        $config = Arr::dot($this->config->get('tenancymodules'));
        foreach ($config as $configKey => $configValue){
            $this->config->set($configKey,$configValue);
        }
    }
}