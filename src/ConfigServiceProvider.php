<?php

namespace Mamdouh\TenancyModules;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Config\Repository;

class ConfigServiceProvider extends ServiceProvider
{
    public function __construct($app, Repository $config)
    {
        parent::__construct($app);
    }
}