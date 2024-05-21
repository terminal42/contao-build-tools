<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = null;

if (file_exists('./depcheck.php')) {
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
        'Swift_Attachment',
        'Swift_EmbeddedFile',
        'Swift_Mailer',
        'Swift_Message',
    ])
    ->disableReportingUnmatchedIgnores()

    // Ignore the Contao components.
    ->ignoreErrorsOnPackage('contao-components/ace', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/chosen', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/colorbox', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/colorpicker', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/contao', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/datepicker', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/dropzone', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/handorgel', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/jquery', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/jquery-ui', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/mediabox', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/mootools', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/simplemodal', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/swipe', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/swiper', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/tablesort', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/tablesorter', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/tinymce4', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('contao-components/tristen-tablesort', [ErrorType::UNUSED_DEPENDENCY])

    // The manager plugin is a dev dependency because it is only required in the
    // managed edition.
    ->ignoreErrorsOnPackage('contao/manager-plugin', [ErrorType::DEV_DEPENDENCY_IN_PROD])
;

return $config;
