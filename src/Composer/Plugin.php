<?php

declare(strict_types=1);

namespace Terminal42\ContaoBuildTools\Composer;

use Composer\Command\BaseCommand;
use Composer\Composer;
use Composer\Console\Application;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class Plugin implements PluginInterface, EventSubscriberInterface, Capable
{
    private const FIX_SCRIPT = 'fix-tools';
    private const CI_SCRIPT = 'build-tools';
    private const LEGACY_MODULES = './system/modules';

    private Filesystem $filesystem;
    public array $activatedScripts = [];
    public array $scriptAliases = [];

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function activate(Composer $composer, IOInterface $io): void
    {
        $scripts = [];
        $isProject = $this->isProject($composer);
        $phpSources = ['./src', './tests', './config'];

        if (!$isProject) {
            $phpSources[] = './bin';
        }

        $this->registerConfigScript(
            ['ecs', 'cs-fixer'],
            'Run code style fixes on the project files [terminal42/contao-build-tools].',
            '@php vendor/terminal42/contao-build-tools/tools/ecs/vendor/bin/ecs check %s --config vendor/terminal42/contao-build-tools/tools/ecs/config/%s.php --fix --ansi',
            '@php vendor/terminal42/contao-build-tools/tools/ecs/vendor/bin/ecs check %s --config vendor/terminal42/contao-build-tools/tools/ecs/config/%s.php --no-progress-bar --no-interaction',
            [
                'default' => $phpSources,
                'contao' => ['./contao', self::LEGACY_MODULES],
                'template' => ['./templates', './contao/templates'],
            ],
            $scripts,
        );

        $this->registerConfigScript(
            'rector',
            'Run Rector on the project files [terminal42/contao-build-tools].',
            '@php vendor/terminal42/contao-build-tools/tools/rector/vendor/bin/rector process %s --config vendor/terminal42/contao-build-tools/tools/rector/%s.php --ansi',
            '@php vendor/terminal42/contao-build-tools/tools/rector/vendor/bin/rector process %s --config vendor/terminal42/contao-build-tools/tools/rector/%s.php --dry-run --no-progress-bar --no-diffs',
            [
                'config' => [...$phpSources, './contao', './templates', self::LEGACY_MODULES]
            ],
            $scripts
        );

        $this->registerConfigScript(
            'phpstan',
            'Run PHPStan on the project files [terminal42/contao-build-tools].',
            '@php vendor/terminal42/contao-build-tools/tools/phpstan/vendor/bin/phpstan analyze %s --ansi --configuration=vendor/terminal42/contao-build-tools/tools/phpstan/%s.php',
            null,
            [
                'config' => [...$phpSources, self::LEGACY_MODULES]
            ],
            $scripts
        );

        $this->registerConfigScript(
            'depcheck',
            'Run Dependency Analyzer on the project files [terminal42/contao-build-tools].',
            '@php vendor/terminal42/contao-build-tools/tools/composer-dependency-analyser/vendor/bin/composer-dependency-analyser --composer-json=%s --config=vendor/terminal42/contao-build-tools/tools/composer-dependency-analyser/%s.php',
            null,
            [
                'config' => ['./composer.json'],
            ],
            $scripts
        );

        $this->registerConfigScript(
            'yamllint',
            'Run yamllint on the project files [terminal42/contao-build-tools].',
            'vendor/terminal42/contao-build-tools/tools/yamllint/vendor/bin/yaml-lint --parse-tags %s',
            null,
            [
                '' => ['./config', './github'],
            ],
            $scripts
        );

        $this->registerConfigScript(
            'stylelint',
            'Run stylelint on the project files [terminal42/contao-build-tools].',
            'vendor/terminal42/contao-build-tools/tools/stylelint/node_modules/.bin/stylelint %s --config vendor/terminal42/contao-build-tools/tools/stylelint/%s --allow-empty-input --fix',
            'vendor/terminal42/contao-build-tools/tools/stylelint/node_modules/.bin/stylelint %s --config vendor/terminal42/contao-build-tools/tools/stylelint/%s --allow-empty-input',
            [
                'stylelint.config.js' => array_filter(['./layout' => './layout/**/*.s?(a|c)ss', './assets' => $isProject ? null : './assets/**/*.s?(a|c)ss']),
            ],
            $scripts
        );

        $this->registerConfigScript(
            'eslint',
            'Run eslint on the project files [terminal42/contao-build-tools].',
            'vendor/terminal42/contao-build-tools/tools/eslint/node_modules/.bin/eslint %s --config vendor/terminal42/contao-build-tools/tools/eslint/%s --resolve-plugins-relative-to vendor/terminal42/contao-build-tools/tools/eslint/ --report-unused-disable-directives --no-error-on-unmatched-pattern --fix',
            'vendor/terminal42/contao-build-tools/tools/eslint/node_modules/.bin/eslint %s --config vendor/terminal42/contao-build-tools/tools/eslint/%s --resolve-plugins-relative-to vendor/terminal42/contao-build-tools/tools/eslint/ --report-unused-disable-directives --no-error-on-unmatched-pattern',
            [
                '.eslintrc.json' => array_filter(['./layout' => './layout/**/*.js', './assets' => $isProject ? null : './assets/**/*.js']),
            ],
            $scripts
        );

        $this->registerConfigScript(
            'biome',
            'Run biome on the project files [terminal42/contao-build-tools].',
            'vendor/terminal42/contao-build-tools/tools/biome/node_modules/.bin/biome check %s --write --unsafe  --config-path=vendor/terminal42/contao-build-tools/tools/biome/%s --no-errors-on-unmatched',
            'vendor/terminal42/contao-build-tools/tools/biome/node_modules/.bin/biome ci %s --config-path=vendor/terminal42/contao-build-tools/tools/biome/%s --no-errors-on-unmatched',
            [
                'biome.json' => array_filter(['./layout' => './layout/', './assets' => $isProject ? null : './assets/']),
            ],
            $scripts,
        );

        $rootPackage = $composer->getPackage();
        $rootPackage->setScripts(
            array_merge(
                array_diff_key($scripts, $rootPackage->getScripts()),
                $rootPackage->getScripts()
            )
        );
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // Nothing to do here
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // Nothing to do here
    }

    public function getCapabilities(): array
    {
        return [CommandProviderCapability::class => CommandProvider::class];
    }

    public function installTools(Event $event): void
    {
        if (!$event->isDevMode()) {
            return;
        }

        $event->getIO()->write('<warning>Installing tools …</warning>');
        $this->executeAllNamespaces(new StringInput('install'), $event->getIO(), $event->getComposer());
    }

    public function updateTools(Event $event): void
    {
        if (!$event->isDevMode()) {
            return;
        }

        $event->getIO()->write('<warning>Updating tools …</warning>');
        $this->executeAllNamespaces(new StringInput('update'), $event->getIO(), $event->getComposer());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'installTools',
            ScriptEvents::POST_UPDATE_CMD => 'updateTools',
        ];
    }

    private function executeAllNamespaces(InputInterface $input, IOInterface $io, Composer $composer): void
    {
        $application = new Application();
        $output = Factory::createOutput();

        $binRoots = glob(__DIR__.'/../../tools/*', GLOB_ONLYDIR);
        if (empty($binRoots)) {
            $io->writeError('<warning>Couldn\'t find any tool namespace.</warning>');

            return;
        }

        $originalWorkingDir = getcwd();
        foreach ($binRoots as $binRoot) {
            if (
                $this->filesystem->exists($binRoot.'/package.json')
                && (
                    $this->filesystem->exists($originalWorkingDir.'/layout')
                    || (
                        $this->filesystem->exists($originalWorkingDir.'/assets')
                        && !$this->isProject($composer)
                    )
                )
            ) {
                Process::fromShellCommandline('npm install')
                    ->setWorkingDirectory($binRoot)
                    ->mustRun(static function (string $type, string $buffer) use ($output) {
                        $output->write($buffer);
                    })
                ;
            }

            if ($this->filesystem->exists($binRoot.'/composer.json')) {
                $this->executeInNamespace($application, $binRoot, $input, $output);

                chdir($originalWorkingDir);
                $this->resetComposers($application);
            }
        }
    }

    private function executeInNamespace(Application $application, $namespace, InputInterface $input, OutputInterface $output): int
    {
        if (!$this->filesystem->exists($namespace)) {
            $this->filesystem->mkdir($namespace);
        }

        chdir($namespace);

        // some plugins require access to composer file e.g. Symfony Flex
        if (!$this->filesystem->exists(Factory::getComposerFile())) {
            $this->filesystem->dumpFile(Factory::getComposerFile(), '{}');
        }

        $input = new StringInput((string) $input . ' --quiet --working-dir=.');

        $output->write('<info>Run with <comment>' . $input->__toString() . '</comment></info>', true, IOInterface::VERBOSE);

        return $application->doRun($input, $output);
    }

    private function resetComposers(Application $application): void
    {
        $application->resetComposer();
        foreach ($application->all() as $command) {
            if ($command instanceof BaseCommand) {
                $command->resetComposer();
            }
        }
    }

    private function registerConfigScript(string|array $name, string $description, string $command, ?string $ciCommand, array $configs, array &$scripts, bool $addToTools = true): void
    {
        $aliases = (array) $name;
        $name = array_shift($aliases);

        foreach ($configs as $config => $paths) {
            $paths = $this->filterPaths($paths);

            if (empty($paths)) {
                continue;
            }

            $this->addScript(
                sprintf($command, '"'.implode('" "', $paths).'"', $config),
                $name,
                $scripts
            );

            if ($addToTools) {
                $this->addScript(
                    sprintf($command, '"'.implode('" "', $paths).'"', $config),
                    self::FIX_SCRIPT,
                    $scripts
                );

                $this->addScript(
                    sprintf($ciCommand ?? $command, '"'.implode('" "', $paths).'"', $config),
                    self::CI_SCRIPT,
                    $scripts
                );
            }
        }

        if (!empty($scripts[$name])) {
            $this->activatedScripts[$name] = $description;
            $this->scriptAliases[$name] = $aliases;

            if ($addToTools) {
                $this->activatedScripts[self::CI_SCRIPT] = 'Run all tools for a CI build chain [terminal42/contao-build-tools].';
                $this->activatedScripts[self::FIX_SCRIPT] = 'Run fixers of all CI tools [terminal42/contao-build-tools].';
            }
        }
    }

    private function filterPaths(array $paths): array
    {
        $result = [];

        foreach ($paths as $source => $path) {
            if (!file_exists(\is_string($source) ? $source : $path)) {
                continue;
            }

            if (self::LEGACY_MODULES === $path) {
                foreach(scandir(self::LEGACY_MODULES) as $dir) {
                    if ('.' === $dir || '..' === $dir) {
                        continue;
                    }

                    if (is_dir(self::LEGACY_MODULES.'/'.$dir) && !is_link(self::LEGACY_MODULES.'/'.$dir)) {
                        $result[] = self::LEGACY_MODULES.'/'.$dir;
                    }
                }

                continue;
            }

            $result[] = $path;
        }

        return $result;
    }

    private function addScript(string $command, string $name, array &$scripts): void
    {
        if (!isset($scripts[$name])) {
            $scripts[$name] = [];
        }

        $scripts[$name][] = $command;
    }

    private function isProject(Composer $composer): bool
    {
        return 'project' === $composer->getPackage()->getType();
    }
}
