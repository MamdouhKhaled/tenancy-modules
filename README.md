## How to Install

* Create laravel app if not already installed
* packages you Are needed to install
    * [Laravel Tanancy](https://tenancyforlaravel.com/)
    * [Laravel Modules](https://nwidart.com/laravel-modules/v6/introduction)
    * [Laravel Tenancy Modules](https://github.com/MamdouhKhaled/tenancy-modules)

```
composer require mamdouhkhaled/tenancy-modules
```

Create Table for manage Modules with Tenancy
```
php artisan tenancy:install // fresh install only
php artisan migrate
```

### Published Configuration
```
php artisan vendor:publish --tag=tenancymodules
```

### Enable / Disable modules Commands
```
php artisan module:tenant-enable --tenant={{tenant-id}} // enable all modules for this tenant
php artisan module:tenant-enable --tenant={{tenant-id}} --module={{module_name}} // enable `module_name` modules for this tenant

php artisan module:tenant-disable --tenant={{tenant-id}} // enable all modules for this tenant
php artisan module:tenant-disable --tenant={{tenant-id}} --module={{module_name}} // enable `module_name` modules for this tenant
```
### Migration / rollback Commands
```
php artisan module:tenant-migrate --tenants={{tenant-id}}// tenant is comma separated
php artisan module:tenant-migrate --tenants={{tenant-id}} {{module}}

php artisan module:tenant-migrate-rollback --tenants={{tenant-id}}
php artisan module:tenant-migrate-rollback --tenants={{tenant-id}} {{module}}
```

### Seed Commands

this Command Run {module}TenantDatabaseSeeder

```
php artisan module:tenant-seed --tenants={{tenant-id}}// tenant is comma separated
php artisan module:tenant-seed --tenants={{tenant-id}} {{module}}
```

### Route
```
as tenant way
```

## Contributing

Thank you for considering contributing to the Laravel Tenancy Modules Package!
## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
