<?php

declare(strict_types=1);

use Composer\Semver\VersionParser;
use Contao\Rector\Set\ContaoLevelSetList;
use Contao\Rector\Set\ContaoSetList;
use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php70\Rector\FuncCall\RandomFunctionRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php81\Rector\Array_\FirstClassCallableRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Symfony42\Rector\MethodCall\ContainerGetToConstructorInjectionRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector;

return static function (RectorConfig $rectorConfig): void {

    if (!file_exists(getcwd().'/composer.json')) {
        throw new \RuntimeException('No composer.json found.');
    }

    $versionParser = new VersionParser();
    $composerJson = json_decode(file_get_contents(getcwd().'/composer.json'), true, 512, JSON_THROW_ON_ERROR);

    if ($contaoConstraint = $composerJson['require']['contao/core-bundle'] ?? $composerJson['require']['contao/manager-bundle'] ?? $composerJson['require-dev']['contao/core-bundle'] ?? $composerJson['require-dev']['contao/manager-bundle'] ?? null) {
        $parsedConstraints = $versionParser->parseConstraints($contaoConstraint);

        $setList = match (true) {
            $parsedConstraints->matches($versionParser->parseConstraints('< 4.9')) => [],
            $parsedConstraints->matches($versionParser->parseConstraints('< 4.10')) => [ContaoLevelSetList::UP_TO_CONTAO_49],
            $parsedConstraints->matches($versionParser->parseConstraints('< 5.0')) => [ContaoLevelSetList::UP_TO_CONTAO_413],
            $parsedConstraints->matches($versionParser->parseConstraints('< 5.1')) => [ContaoLevelSetList::UP_TO_CONTAO_50],
            $parsedConstraints->matches($versionParser->parseConstraints('^5.1')) => [ContaoLevelSetList::UP_TO_CONTAO_51],
        };

        if (!empty($setList)) {
            $rectorConfig->sets($setList);
        }
    }

    if ($phpConstraint = $composerJson['config']['platform']['php'] ?? $composerJson['require']['php'] ?? null) {
        $parsedConstraints = $versionParser->parseConstraints($phpConstraint);

        $setList = match (true) {
            $parsedConstraints->matches($versionParser->parseConstraints('< 7.1')) => [],
            $parsedConstraints->matches($versionParser->parseConstraints('< 7.2')) => [LevelSetList::UP_TO_PHP_71],
            $parsedConstraints->matches($versionParser->parseConstraints('< 7.3')) => [LevelSetList::UP_TO_PHP_72],
            $parsedConstraints->matches($versionParser->parseConstraints('< 7.4')) => [LevelSetList::UP_TO_PHP_73],
            $parsedConstraints->matches($versionParser->parseConstraints('< 8.0')) => [LevelSetList::UP_TO_PHP_74],
            $parsedConstraints->matches($versionParser->parseConstraints('< 8.1')) => [LevelSetList::UP_TO_PHP_80],
            $parsedConstraints->matches($versionParser->parseConstraints('^8.1')) => [
                LevelSetList::UP_TO_PHP_81,
                ContaoSetList::ANNOTATIONS_TO_ATTRIBUTES,
                DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
            ],
        };

        if (!empty($setList)) {
            $rectorConfig->sets($setList);
        }
    }

    // https://getrector.com/blog/5-common-mistakes-in-rector-config-and-how-to-avoid-them
    $rectorConfig->sets([
        SetList::DEAD_CODE,
        //SetList::CODE_QUALITY,
        //SetList::CODING_STYLE,
        //SetList::NAMING,
        //SetList::TYPE_DECLARATION,
        //SetList::PRIVATIZATION,
        //SetList::EARLY_RETURN,
        //SetList::INSTANCEOF,
    ]);

    $rectorConfig->symfonyContainerPhp(__DIR__ . '/tests/symfony-container.php');

    $rectorConfig->skip([
        ClassPropertyAssignToConstructorPromotionRector::class => [
            '*/Entity/'
        ],
        TypedPropertyFromAssignsRector::class => [
            '*/Entity/'
        ],
        ContainerGetToConstructorInjectionRector::class => [
            '*/Model/',
            '*/FormField/',
            '*/FrontendModule/',
            '*/ContentElement/',
        ],

        // Make sure hooks and callbacks are not converted to first class callables
        FirstClassCallableRector::class => [
            '*/config.php',
            '*/dca/*.php',
        ],

        // Allow rand() in templates (e.g. for Isotope eCommerce)
        RandomFunctionRector::class => [
            '*.html5'
        ],
    ]);

    $rectorConfig->fileExtensions(['php', 'html5']);
    $rectorConfig->parallel();

    if (file_exists(getcwd().'/rector.php')) {
        $rectorConfig->import(getcwd().'/rector.php');
    }
};
