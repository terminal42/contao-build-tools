<?php

declare(strict_types=1);

use Contao\CoreBundle\Twig\Defer\DeferTokenParser;
use Contao\CoreBundle\Twig\ResponseContext\AddTokenParser;
use Contao\CoreBundle\Twig\Slots\SlotTokenParser;
use TwigCsFixer\Config\Config;
use TwigCsFixer\Rules\Node\ForbiddenFunctionRule;

@include_once __DIR__.'/../../../../contao/core-bundle/src/Twig/Defer/DeferredBlockReferenceNode.php';
@include_once __DIR__.'/../../../../contao/core-bundle/src/Twig/Defer/DeferTokenParser.php';
@include_once __DIR__.'/../../../../contao/core-bundle/src/Twig/ResponseContext/AddNode.php';
@include_once __DIR__.'/../../../../contao/core-bundle/src/Twig/ResponseContext/AddTokenParser.php';
@include_once __DIR__.'/../../../../contao/core-bundle/src/Twig/ResponseContext/DocumentLocation.php';
@include_once __DIR__.'/../../../../contao/core-bundle/src/Twig/Slots/SlotNode.php';
@include_once __DIR__.'/../../../../contao/core-bundle/src/Twig/Slots/SlotTokenParser.php';

/** @var Config $config */
$config = require __DIR__.'/../code-quality-tools/vendor/terminal42/code-quality-tools/tools/twig-cs-fixer/config.php';

$ruleset = $config->getRuleset();

$ruleset->addRule(new ForbiddenFunctionRule([
    'contao_figure', // you should use the "figure" function instead
    'insert_tag', // you should not misuse insert tags in templates
    'contao_section', // only for legacy layouts
    'contao_sections', // only for legacy layouts
]));

if (class_exists(DeferTokenParser::class)) {
    $config->addTokenParser(new DeferTokenParser());
}

if (class_exists(AddTokenParser::class)) {
    $config->addTokenParser(new AddTokenParser(''));
}

if (class_exists(SlotTokenParser::class)) {
    $config->addTokenParser(new SlotTokenParser());
}

return $config;
