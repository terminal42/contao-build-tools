<?php

declare(strict_types=1);

use Contao\Rector\Set\ContaoLevelSetList;
use Contao\Rector\Set\ContaoSetList;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {

    $rectorConfig->paths([getcwd().'/src']);
    $rectorConfig->sets([ContaoLevelSetList::UP_TO_CONTAO_413]);

    if (file_exists(getcwd().'/composer.json')) {
        $composerJson = json_decode(file_get_contents(getcwd().'/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        switch($composerJson['require']['php'] ?? null) {
            case '^8.1':
            case '8.1.*':
                $rectorConfig->sets([LevelSetList::UP_TO_PHP_81, ContaoSetList::ANNOTATIONS_TO_ATTRIBUTES]);
                break;
        }
    }

    require_once getcwd().'/vendor/contao/core-bundle/src/Resources/contao/config/constants.php';

    if (!\defined('TL_MODE')) {
        \define('TL_MODE', 'FE');
        \define('TL_START', microtime(true));
        \define('TL_ROOT', getcwd());
        \define('TL_REFERER_ID', '');
        \define('TL_SCRIPT', 'index.php');
        \define('BE_USER_LOGGED_IN', false);
        \define('FE_USER_LOGGED_IN', false);
        \define('TL_PATH', '/');
    }

    $rectorConfig->parallel();
};
