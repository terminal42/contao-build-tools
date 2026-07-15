<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

$builder = ECSConfig::configure()
    ->withSets([__DIR__.'/../code-quality-tools/vendor/terminal42/code-quality-tools/tools/ecs/config.php'])
    ->withCache(sys_get_temp_dir().'/ecs_default_cache')
;

return new class($builder) {
    public function __construct(private $builder)
    {
    }

    public function __invoke(ECSConfig $ecsConfig): void
    {
        ($this->builder)($ecsConfig);

        $rootConfigFile = getcwd().'/ecs.php';
        if (!file_exists($rootConfigFile)) {
            return;
        }

        $rootConfig = require $rootConfigFile;
        if (is_callable($rootConfig)) {
            $rootConfig($ecsConfig);
        }
    }
};
