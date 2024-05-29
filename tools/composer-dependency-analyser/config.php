<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

if (!file_exists(getcwd().'/composer.json')) {
    throw new \RuntimeException('No composer.json found.');
}

$config = null;

if (file_exists('./composer-dependency-analyser.php')) {
    trigger_error('Using config '.getcwd().'/composer-dependency-analyser.php');
    $config = require('./composer-dependency-analyser.php');
} elseif (file_exists('./depcheck.php')) {
    trigger_error('Using config '.getcwd().'/depcheck.php');
    trigger_error('Please rename your "depcheck.php" file to "composer-dependency-analyser.php".', E_USER_DEPRECATED);
    $config = require('./depcheck.php');
}

if (!$config instanceof Configuration) {
    $config = new Configuration();
}

if (empty($config->getPathsToScan())) {
    $paths = [
        './src' => false,
        './config' => false,
        './contao' => false,
        './templates' => false,
        './tests' => true,
    ];

    foreach ($paths as $path => $isDev) {
        if (file_exists($path)) {
            $config->addPathToScan($path, $isDev);
        }
    }
}

$config
    ->ignoreUnknownClasses([
        'Gmagick',
        'Imagick',
    ])
    ->enableAnalysisOfUnusedDevDependencies()
    ->disableReportingUnmatchedIgnores()

    ->ignoreErrorsOnPackage('terminal42/contao-build-tools', [ErrorType::UNUSED_DEPENDENCY])
;

if (file_exists('./deploy.php')) {
    $config->ignoreErrorsOnPackage('deployer/deployer', [ErrorType::UNUSED_DEPENDENCY]);
}

$composerJson = json_decode(file_get_contents(getcwd().'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$isBundle = \in_array($composerJson['type'] ?? null, ['contao-bundle', 'contao-module']);
$isProject = isset($composerJson['require']['contao/manager-bundle']);

if ($isBundle) {
    // The manager plugin is a dev dependency because it is only required in the managed edition.
    $config->ignoreErrorsOnPackage('contao/manager-plugin', [ErrorType::DEV_DEPENDENCY_IN_PROD]);
} elseif ($isProject) {
    $config
        ->ignoreErrorsOnPackage('contao/conflicts', [ErrorType::UNUSED_DEPENDENCY])
        ->ignoreErrorsOnPackage('contao/manager-bundle', [ErrorType::UNUSED_DEPENDENCY])
        ->ignoreErrorsOnPackage('contao/core-bundle', [ErrorType::SHADOW_DEPENDENCY])
    ;
}

// Ignore all Contao bundles, they might add features or DCA fields etc.
foreach (array_keys($composerJson['require']) as $packageName) {
    if (file_exists(getcwd().'/vendor/'.$packageName.'/composer.json')) {
        $data = json_decode(file_get_contents(getcwd().'/vendor/'.$packageName.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        if (\in_array($data['type'] ?? null, ['contao-bundle', 'contao-module'])) {
            $config->ignoreErrorsOnPackage($packageName, [ErrorType::UNUSED_DEPENDENCY]);
        }
    }
}

return $config;
