<?php

declare(strict_types=1);

use Composer\Semver\VersionParser;
use Contao\Rector\Set\ContaoLevelSetList;
use Rector\Config\RectorConfig;
use Rector\Php70\Rector\FuncCall\RandomFunctionRector;

return static function (RectorConfig $rectorConfig): void {
    if (!file_exists(getcwd().'/composer.json')) {
        throw new RuntimeException('No composer.json found.');
    }

    $rectorConfig->import(__DIR__.'/../code-quality-tools/vendor/terminal42/code-quality-tools/tools/rector/config.php');

    $versionParser = new VersionParser();
    $composerJson = json_decode(file_get_contents(getcwd().'/composer.json'), true, 512, JSON_THROW_ON_ERROR);

    if ($contaoConstraint = $composerJson['require']['contao/core-bundle'] ?? $composerJson['require']['contao/manager-bundle'] ?? $composerJson['require-dev']['contao/core-bundle'] ?? $composerJson['require-dev']['contao/manager-bundle'] ?? null) {
        $parsedConstraints = $versionParser->parseConstraints($contaoConstraint);

        $setList = match (true) {
            $parsedConstraints->matches($versionParser->parseConstraints('< 4.9')) => [],
            $parsedConstraints->matches($versionParser->parseConstraints('< 4.10')) => [ContaoLevelSetList::UP_TO_CONTAO_49],
            $parsedConstraints->matches($versionParser->parseConstraints('< 5.0')) => [ContaoLevelSetList::UP_TO_CONTAO_413],
            $parsedConstraints->matches($versionParser->parseConstraints('< 5.1')) => [ContaoLevelSetList::UP_TO_CONTAO_50],
            $parsedConstraints->matches($versionParser->parseConstraints('< 5.3')) => [ContaoLevelSetList::UP_TO_CONTAO_51],
            $parsedConstraints->matches($versionParser->parseConstraints('< 5.4')) => [ContaoLevelSetList::UP_TO_CONTAO_53],
            $parsedConstraints->matches($versionParser->parseConstraints('< 5.7')) => [ContaoLevelSetList::UP_TO_CONTAO_55],
            $parsedConstraints->matches($versionParser->parseConstraints('^5.7')) => [ContaoLevelSetList::UP_TO_CONTAO_57],
        };

        if (!empty($setList)) {
            $rectorConfig->sets($setList);
        }
    }

    $rectorConfig->symfonyContainerPhp(__DIR__.'/symfony-container.php');

    $rectorConfig->skip([
        // Allow rand() in templates (e.g. for Isotope eCommerce)
        RandomFunctionRector::class => [
            '*.html5',
        ],
    ]);

    $rectorConfig->fileExtensions(['php', 'html5']);

    if (file_exists(getcwd().'/rector.php')) {
        $rectorConfig->import(getcwd().'/rector.php');
    }
};
