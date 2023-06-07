<?php

use Mamdouh\TenancyModules\Activators\DataBaseActivator;
use Mamdouh\TenancyModules\Activators\FileActivator;

return [

    'modules' => [
        'paths' => [
            'migration' => base_path('Database/Migrations/Tenant'),
            'generator' => [
                'migration' => [
                    'path' => 'Database/Migrations/Tenant',
                    'generate' => true
                ]
            ]
        ],

        'activators' => [
            'file' => [
                'class' => FileActivator::class,
                'statuses-file' => base_path('modules_statuses.json'),
                'cache-key' => 'activator.installed',
                'cache-lifetime' => 604800,
            ],
            'database' => [
                'class' => DataBaseActivator::class,
                'statuses-file' => base_path('modules_statuses.json'),
                'cache-key' => 'activator.installed',
                'cache-lifetime' => 604800,
            ],
        ],

        'activator' => 'database',
    ]
];