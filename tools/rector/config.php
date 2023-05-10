<?php

declare(strict_types=1);

use Contao\Rector\Set\ContaoLevelSetList;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([getcwd().'/src']);

    $rectorConfig->sets([ContaoLevelSetList::UP_TO_CONTAO_413]);

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
