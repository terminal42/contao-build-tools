<?php

use Contao\CoreBundle\Twig\Defer\DeferTokenParser;
use Contao\CoreBundle\Twig\ResponseContext\AddTokenParser;
use Contao\CoreBundle\Twig\Slots\SlotTokenParser;
use TwigCsFixer\Config\Config;
use TwigCsFixer\File\Finder;
use TwigCsFixer\Rules\File\DirectoryNameRule;
use TwigCsFixer\Rules\File\FileExtensionRule;
use TwigCsFixer\Rules\File\FileNameRule;
use TwigCsFixer\Rules\Literal\CompactHashRule;
use TwigCsFixer\Rules\Node\ForbiddenFunctionRule;
use TwigCsFixer\Rules\Node\ValidConstantFunctionRule;
use TwigCsFixer\Rules\Variable\VariableNameRule;
use TwigCsFixer\Ruleset\Ruleset;
use TwigCsFixer\Standard\TwigCsFixer;

require_once __DIR__ . '/vendor/autoload.php';
@include_once __DIR__ . '/core-bundle/src/Twig/Defer/DeferredBlockReferenceNode.php';
@include_once __DIR__ . '/core-bundle/src/Twig/Defer/DeferTokenParser.php';
@include_once __DIR__ . '/core-bundle/src/Twig/ResponseContext/AddNode.php';
@include_once __DIR__ . '/core-bundle/src/Twig/ResponseContext/AddTokenParser.php';
@include_once __DIR__ . '/core-bundle/src/Twig/ResponseContext/DocumentLocation.php';
@include_once __DIR__ . '/core-bundle/src/Twig/Slots/SlotNode.php';
@include_once __DIR__ . '/core-bundle/src/Twig/Slots/SlotTokenParser.php';

$ruleset = new Ruleset();
$ruleset->addStandard(new TwigCsFixer());

$ruleset->overrideRule(new CompactHashRule(true));
$ruleset->overrideRule(new VariableNameRule(optionalPrefix: '_'));

$ruleset->addRule(new FileExtensionRule());
$ruleset->addRule(new ValidConstantFunctionRule());

$ruleset->addRule(new ForbiddenFunctionRule([
    'contao_figure', // you should use the "figure" function instead
    'insert_tag', // you should not misuse insert tags in templates
    'contao_section', // only for legacy layouts
    'contao_sections', // only for legacy layouts
]));

$config = new Config();
$config->allowNonFixableRules();

if (class_exists(DeferTokenParser::class)) {
    $config->addTokenParser(new DeferTokenParser());
}

if (class_exists(AddTokenParser::class)) {
    $config->addTokenParser(new AddTokenParser(''));
}

if (class_exists(SlotTokenParser::class)) {
    $config->addTokenParser(new SlotTokenParser());
}

$config->setRuleset($ruleset);
$config->setCacheFile(sys_get_temp_dir().'/twig-cs-fixer');

return $config;
