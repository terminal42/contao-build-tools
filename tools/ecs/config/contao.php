<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Terminal42\ContaoBuildTools\BuildToolsConfig;

require_once __DIR__.'/../../../src/BuildToolsConfig.php';
$buildToolsConfig = new BuildToolsConfig((string) getcwd());
$directories = $buildToolsConfig->getDirectories();

$builder = ECSConfig::configure()
    ->withSets([__DIR__.'/default.php'])
    ->withSkip([
        '*/templates/*',
        DeclareStrictTypesFixer::class,
    ])
    ->withCache(sys_get_temp_dir().'/ecs_contao_cache')
;

return new class($builder, $directories) {
    public function __construct(
        private $builder,
        private array $directories,
    ) {
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
            $rootConfig($ecsConfig, $this->directories);
        }
    }
};
