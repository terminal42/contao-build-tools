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
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Symfony72\Rector\StmtsAwareInterface\PushRequestToRequestStackConstructorRector;

return static function (RectorConfig $rectorConfig): void {

    if (!file_exists(getcwd().'/composer.json')) {
        throw new \RuntimeException('No composer.json found.');
    }

    $versionParser = new VersionParser();
    $composerJson = json_decode(file_get_contents(getcwd().'/composer.json'), true, 512, JSON_THROW_ON_ERROR);

    if ($contaoConstraint = $composerJson['require']['contao/core-bundle'] ?? $composerJson['require']['contao/manager-bundle'] ?? $composerJson['require-dev']['contao/core-bundle'] ?? $composerJson['require-dev']['contao/manager-bundle'] ?? null) {
        $parsedConstraints = $versionParser->parseConstraints($contaoConstraint);
        $lowerBound = $parsedConstraints->getLowerBound();
        $isContao4 = $parsedConstraints->matches($versionParser->parseConstraints('< 5.0'));

        $setList = match (true) {
            $lowerBound->compareTo($versionParser->parseConstraints('< 4.9')->getLowerBound(), '>') => [],
            $lowerBound->compareTo($versionParser->parseConstraints('< 4.10')->getLowerBound(), '>') => [ContaoLevelSetList::UP_TO_CONTAO_49],
            $isContao4 => [ContaoLevelSetList::UP_TO_CONTAO_413],
            $lowerBound->compareTo($versionParser->parseConstraints('< 5.1')->getLowerBound(), '>') => [ContaoLevelSetList::UP_TO_CONTAO_50],
            $lowerBound->compareTo($versionParser->parseConstraints('< 5.3')->getLowerBound(), '>') => [ContaoLevelSetList::UP_TO_CONTAO_51],
            $lowerBound->compareTo($versionParser->parseConstraints('< 5.4')->getLowerBound(), '>') => [ContaoLevelSetList::UP_TO_CONTAO_53],
            $lowerBound->compareTo($versionParser->parseConstraints('< 5.7')->getLowerBound(), '>') => [ContaoLevelSetList::UP_TO_CONTAO_55],
            $lowerBound->compareTo($versionParser->parseConstraints('^5.7')->getLowerBound(), '>') => [ContaoLevelSetList::UP_TO_CONTAO_57],
        };

        if (!empty($setList)) {
            $rectorConfig->sets($setList);
        }

        if ($isContao4) {
            // Request stack constructor argument is not available in Contao 4.13
            $rectorConfig->skip([PushRequestToRequestStackConstructorRector::class]);
        }
    }

    if ($phpConstraint = $composerJson['config']['platform']['php'] ?? $composerJson['require']['php'] ?? null) {
        $lowerBound = $versionParser->parseConstraints($phpConstraint)->getLowerBound();

        $setList = match (true) {
            $lowerBound->compareTo($versionParser->parseConstraints('< 7.1')->getLowerBound(), '>') => [],
            $lowerBound->compareTo($versionParser->parseConstraints('< 7.2')->getLowerBound(), '>') => [LevelSetList::UP_TO_PHP_71],
            $lowerBound->compareTo($versionParser->parseConstraints('< 7.3')->getLowerBound(), '>') => [LevelSetList::UP_TO_PHP_72],
            $lowerBound->compareTo($versionParser->parseConstraints('< 7.4')->getLowerBound(), '>') => [LevelSetList::UP_TO_PHP_73],
            $lowerBound->compareTo($versionParser->parseConstraints('< 8.0')->getLowerBound(), '>') => [LevelSetList::UP_TO_PHP_74],
            $lowerBound->compareTo($versionParser->parseConstraints('< 8.1')->getLowerBound(), '>') => [LevelSetList::UP_TO_PHP_80],
            $lowerBound->compareTo($versionParser->parseConstraints('< 8.2')->getLowerBound(), '>') => [LevelSetList::UP_TO_PHP_81, ContaoSetList::ANNOTATIONS_TO_ATTRIBUTES, DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES],
            $lowerBound->compareTo($versionParser->parseConstraints('< 8.3')->getLowerBound(), '>') => [LevelSetList::UP_TO_PHP_82, ContaoSetList::ANNOTATIONS_TO_ATTRIBUTES, DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES],
            $lowerBound->compareTo($versionParser->parseConstraints('< 8.4')->getLowerBound(), '>') => [LevelSetList::UP_TO_PHP_83, ContaoSetList::ANNOTATIONS_TO_ATTRIBUTES, DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES],
            $lowerBound->compareTo($versionParser->parseConstraints('< 8.5')->getLowerBound(), '>') => [LevelSetList::UP_TO_PHP_84, ContaoSetList::ANNOTATIONS_TO_ATTRIBUTES, DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES],
            $lowerBound->compareTo($versionParser->parseConstraints('^8.5')->getLowerBound(), '>') => [LevelSetList::UP_TO_PHP_85, ContaoSetList::ANNOTATIONS_TO_ATTRIBUTES, DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES],
        };

        if (!empty($setList)) {
            $rectorConfig->sets($setList);
        }
    }

    if ($phpunitConstraint = $composerJson['require-dev']['phpunit/phpunit'] ?? null) {
        $lowerBound = $versionParser->parseConstraints($phpunitConstraint)->getLowerBound();

        $setList = [
            '>= 4.0' => PHPUnitSetList::PHPUNIT_40,
            '>= 5.0' => PHPUnitSetList::PHPUNIT_50,
            '>= 6.0' => PHPUnitSetList::PHPUNIT_60,
            '>= 7.0' => PHPUnitSetList::PHPUNIT_70,
            '>= 8.0' => PHPUnitSetList::PHPUNIT_80,
            '>= 9.0' => PHPUnitSetList::PHPUNIT_90,
            '>= 10.0' => [PHPUnitSetList::PHPUNIT_100, PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES],
            '>= 11.0' => PHPUnitSetList::PHPUNIT_110,
            '>= 12.0' => PHPUnitSetList::PHPUNIT_120,
        ];

        $setList = array_filter(
            $setList,
            static fn ($constraint) => $lowerBound->compareTo($versionParser->parseConstraints($constraint)->getLowerBound(), '>'),
            ARRAY_FILTER_USE_KEY,
        );

        if (!empty($setList)) {
            $setList[] = PHPUnitSetList::PHPUNIT_CODE_QUALITY;
            $rectorConfig->sets(array_values($setList));
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
