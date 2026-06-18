<?php

declare(strict_types=1);

use Composer\Semver\VersionParser;
use Contao\EasyCodingStandard\Fixer\CommentLengthFixer;
use Contao\EasyCodingStandard\Fixer\TypeHintOrderFixer;
use PhpCsFixer\Fixer\Comment\HeaderCommentFixer;
use PhpCsFixer\Fixer\Whitespace\MethodChainingIndentationFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

$skip = [
    CommentLengthFixer::class,
    MethodChainingIndentationFixer::class => [
        '*/DependencyInjection/Configuration.php',
    ],
];

if (file_exists(getcwd().'/composer.json')) {
    $versionParser = new VersionParser();
    $composerJson = json_decode(file_get_contents(getcwd().'/composer.json'), true, 512, JSON_THROW_ON_ERROR);

    if ($phpConstraint = $composerJson['config']['platform']['php'] ?? $composerJson['require']['php'] ?? null) {
        $parsedConstraints = $versionParser->parseConstraints($phpConstraint);

        if ($parsedConstraints->matches($versionParser->parseConstraints('< 8'))) {
            $skip[] = TypeHintOrderFixer::class;
        }
    }
}

$builder = ECSConfig::configure()
    ->withSets([__DIR__.'/../vendor/contao/easy-coding-standard/config/contao.php'])
    ->withConfiguredRule(HeaderCommentFixer::class, ['header' => ''])
    ->withSkip($skip)
    ->withParallel()
    ->withSpacing(null, "\n")
    ->withCache(sys_get_temp_dir().'/ecs_default_cache');

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
