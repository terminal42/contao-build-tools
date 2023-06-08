<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use Contao\Rector\Set\ContaoLevelSetList;
use Contao\Rector\Set\ContaoSetList;
use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {

    $versionParser = new VersionParser();

    $setList = match (true) {
        InstalledVersions::satisfies($versionParser, 'contao/core-bundle', '4.9.*') => ContaoLevelSetList::UP_TO_CONTAO_49,
        InstalledVersions::satisfies($versionParser, 'contao/core-bundle', '4.13.*') => ContaoLevelSetList::UP_TO_CONTAO_413,
        InstalledVersions::satisfies($versionParser, 'contao/core-bundle', '5.0.*') => ContaoLevelSetList::UP_TO_CONTAO_50,
        InstalledVersions::satisfies($versionParser, 'contao/core-bundle', '5.1.*') => ContaoLevelSetList::UP_TO_CONTAO_51,
    };

    $rectorConfig->sets([$setList]);

    if (file_exists(getcwd().'/composer.json')) {
        $composerJson = json_decode(file_get_contents(getcwd().'/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        switch($composerJson['require']['php'] ?? null) {
            case '^8.1':
            case '8.1.*':
                $rectorConfig->sets([
                    LevelSetList::UP_TO_PHP_81,
                    ContaoSetList::ANNOTATIONS_TO_ATTRIBUTES,
                    DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
                ]);
                break;
        }
    }

    if (file_exists(getcwd().'/vendor/contao/core-bundle/src/Resources/contao/config/constants.php')) {
        require_once getcwd().'/vendor/contao/core-bundle/src/Resources/contao/config/constants.php';
    }

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
