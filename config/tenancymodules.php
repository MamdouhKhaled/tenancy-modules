<?php

use Mamdouh\TenancyModules\Activators\DataBaseActivator;
use Mamdouh\TenancyModules\Models\Module;

return [
    'model' => Module::class,
    'modules' => [
        'paths' => [
            'migration' => base_path('database/migrations/tenant'),
            'generator' => [
                'migration' => [
                    'path' => 'database/migrations/tenant',
                    'generate' => true,
                ],
            ],
        ],

        'activators' => [
            'database' => [
                'class' => DataBaseActivator::class,
            ],
        ],

        'activator' => 'database',
    ],
];
