<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->sets([__DIR__.'/default.php']);

    $ecsConfig->skip([
        '*/templates/*',
        DeclareStrictTypesFixer::class,
    ]);

    $ecsConfig->cacheDirectory(sys_get_temp_dir().'/ecs_contao_cache');

    if (file_exists(getcwd().'/ecs.php')) {
        $ecsConfig->import(getcwd().'/ecs.php');
    }
};
