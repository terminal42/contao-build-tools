<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

$builder = ECSConfig::configure()
    ->withSets([__DIR__.'/default.php'])
    ->withSkip([
        '*/templates/*',
        DeclareStrictTypesFixer::class,
    ])
    ->withCache(sys_get_temp_dir().'/ecs_contao_cache');

return new class ($builder) {
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
