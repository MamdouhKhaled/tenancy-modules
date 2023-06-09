<?php

namespace Mamdouh\TenancyModules\Activators;

use Illuminate\Container\Container;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Mamdouh\TenancyModules\IdentificationTenant;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Module;

class DataBaseActivator implements ActivatorInterface
{
    protected $tenant;
    private $cache;
    private $files;
    private $config;
    private $cacheKey;
    private $cacheLifetime;
    private $statusesFile;
    protected $modulesStatuses;
    protected static $tenancy;
    public static function setTenant($tenant)
    {
        self::$tenancy = $tenant;
    }
    public function __construct(Container $app)
    {
        if (!app()->runningInConsole()) {
            self::$tenancy = app(IdentificationTenant::class)->getTenant();
        }
        $this->cache = $app['cache'];
        $this->files = $app['files'];
        $this->config = $app['config'];
        $this->statusesFile = $this->config('statuses-file');
        $this->cacheKey = $this->config('cache-key');
        $this->cacheLifetime = $this->config('cache-lifetime');
        $this->modulesStatuses = $this->getModulesStatuses();
    }

    /**
     * @inheritDoc
     */
    public function enable(Module $module): void
    {
        $this->config->get('tenancymodules.model')::where([
            'tenant_id' => self::$tenancy,
            'name' => $module->getName()
        ])->update(['isActive'=>true]);
    }

    /**
     * @inheritDoc
     */
    public function disable(Module $module): void
    {
        $this->config->get('tenancymodules.model')::where([
            'tenant_id' => self::$tenancy,
            'name' => $module->getName()
        ])->update(['isActive'=>false]);
    }

    /**
     * @inheritDoc
     */
    public function hasStatus(Module $module, bool $status): bool
    {
        $back = false;
        if($req = $this->config->get('tenancymodules.model')::where([
            'tenant_id' => self::$tenancy,
            'name' => $module->getName()
        ])->first()){
            $back = $req->isActive;
        }
        return  $back;
    }

    /**
     * @inheritDoc
     */
    public function setActive(Module $module, bool $active): void
    {
        // TODO: Implement setActive() method.
    }

    /**
     * @inheritDoc
     */
    public function setActiveByName(string $name, bool $active): void
    {
        // TODO: Implement setActiveByName() method.
    }

    /**
     * @inheritDoc
     */
    public function delete(Module $module): void
    {
        // TODO: Implement delete() method.
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        // TODO: Implement reset() method.
    }

    private function writeJson(): void
    {
        $this->files->put($this->statusesFile, json_encode($this->modulesStatuses, JSON_PRETTY_PRINT));
    }

    /**
     * Reads the json file that contains the activation statuses.
     * @return array
     * @throws FileNotFoundException
     */
    private function readJson(): array
    {
        if (!$this->files->exists($this->statusesFile)) {
            return [];
        }

        return json_decode($this->files->get($this->statusesFile), true);
    }

    private function getModulesStatuses(): array
    {
        if (!$this->config->get('modules.cache.enabled')) {
            return $this->readJson();
        }

        return $this->cache->store($this->config->get('modules.cache.driver'))->remember($this->cacheKey, $this->cacheLifetime, function () {
            return $this->readJson();
        });
    }

    /**
     * Reads a config parameter under the 'activators.file' key
     *
     * @param  string $key
     * @param  $default
     * @return mixed
     */
    private function config(string $key, $default = null)
    {
        return $this->config->get('modules.activators.database.' . $key, $default);
    }

    /**
     * Flushes the modules activation statuses cache
     */
    private function flushCache(): void
    {
        $this->cache->store($this->config->get('modules.cache.driver'))->forget($this->cacheKey);
    }
}
