<?php

namespace Mamdouh\TenancyModules;

use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use JetBrains\PhpStorm\NoReturn;
use Mamdouh\TenancyModules\Commands\TenantDisableCommand;
use Mamdouh\TenancyModules\Commands\TenantEnableCommand;
use Mamdouh\TenancyModules\Commands\TenantMigrateCommand;
use Mamdouh\TenancyModules\Commands\TenantMigrateRollbackCommand;
use Illuminate\Contracts\Config\Repository;
use Mamdouh\TenancyModules\Commands\TenantSeedCommand;

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
        $this->publishes([
            __DIR__.'/../config/tenancymodules.php' => config_path('tenancymodules.php'),
        ], 'tenancymodules');
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
                TenantDisableCommand::class,
                TenantEnableCommand::class,
                TenantSeedCommand::class,
            ]);
        }
    }

    private function configOverwrite() :void
    {
        $this->config = $this->app->make(Repository::class);
        $tenancymodules = $this->config->get('tenancymodules');
        unset($tenancymodules['modules']['paths']);
        $config = Arr::dot($tenancymodules);
        foreach ($config as $configKey => $configValue){
            $this->config->set($configKey,$configValue);
        }
    }
}
