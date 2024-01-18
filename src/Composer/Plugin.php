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

class Plugin implements PluginInterface, EventSubscriberInterface, Capable
{
    private const CI_SCRIPT = 'build-tools';
    private const LEGACY_MODULES = './system/modules';

    private Filesystem $filesystem;
    public array $activatedScripts = [];

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function activate(Composer $composer, IOInterface $io): void
    {
        if (!$this->filesystem->exists(getcwd().'/src')) {
            return;
        }

        $rootPackage = $composer->getPackage();
        $scripts = $rootPackage->getScripts();

        $this->registerConfigScript(
            'cs-fixer',
            'Run code style fixes on the project files [terminal42/contao-build-tools].',
            '@php vendor/terminal42/contao-build-tools/tools/ecs/vendor/bin/ecs check %s --config vendor/terminal42/contao-build-tools/tools/ecs/config/%s.php --fix --ansi',
            '@php vendor/terminal42/contao-build-tools/tools/ecs/vendor/bin/ecs check %s --config vendor/terminal42/contao-build-tools/tools/ecs/config/%s.php --no-progress-bar --no-interaction',
            [
                'default' => ['./src', './tests'],
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
                'config' => ['./src', './tests', './contao', './templates', self::LEGACY_MODULES]
            ],
            $scripts
        );

        $this->registerConfigScript(
            'phpstan',
            'Run PHPStan on the project files [terminal42/contao-build-tools].',
            '@php vendor/terminal42/contao-build-tools/tools/phpstan/vendor/bin/phpstan analyze %s --ansi --configuration=vendor/terminal42/contao-build-tools/tools/phpstan/%s.php',
            '@php vendor/terminal42/contao-build-tools/tools/phpstan/vendor/bin/phpstan analyze %s --ansi --configuration=vendor/terminal42/contao-build-tools/tools/phpstan/%s.php',
            [
                'config' => ['./src', './tests', self::LEGACY_MODULES]
            ],
            $scripts
        );

        $composer->getPackage()->setScripts($scripts);
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
        $this->executeAllNamespaces(new StringInput('install'), $event->getIO());
    }

    public function updateTools(Event $event): void
    {
        if (!$event->isDevMode()) {
            return;
        }

        $event->getIO()->write('<warning>Updating tools …</warning>');
        $this->executeAllNamespaces(new StringInput('update'), $event->getIO());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'installTools',
            ScriptEvents::POST_UPDATE_CMD => 'updateTools',
        ];
    }

    private function executeAllNamespaces(InputInterface $input, IOInterface $io): void
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
            $this->executeInNamespace($application, $binRoot, $input, $output);

            chdir($originalWorkingDir);
            $this->resetComposers($application);
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

    private function registerConfigScript(string $name, string $description, string $command, string $ciCommand, array $configs, array &$scripts): void
    {
        foreach ($configs as $config => $paths) {
            $paths = $this->filterPaths($paths);

            if (empty($paths)) {
                continue;
            }

            $this->addScript(
                sprintf($command, implode(' ', $paths), $config),
                $name,
                $scripts
            );

            $this->addScript(
                sprintf($ciCommand, implode(' ', $paths), $config),
                self::CI_SCRIPT,
                $scripts
            );
        }

        if (!empty($scripts[$name])) {
            $this->activatedScripts[$name] = $description;
            $this->activatedScripts[self::CI_SCRIPT] = 'Run all tools for a CI build chain [terminal42/contao-build-tools].';
        }
    }

    private function filterPaths(array $paths): array
    {
        $result = [];

        foreach ($paths as $path) {
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

            if (is_dir($path)) {
                $result[] = $path;
            }
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
}
