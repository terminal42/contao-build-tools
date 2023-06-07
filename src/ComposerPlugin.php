<?php

declare(strict_types=1);

namespace Terminal42\ContaoBuildTools;

use Composer\Command\BaseCommand;
use Composer\Composer;
use Composer\Console\Application;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;

class ComposerPlugin implements PluginInterface, EventSubscriberInterface
{
    public function activate(Composer $composer, IOInterface $io): void
    {
        if (!file_exists(getcwd().'/src')) {
            return;
        }

        $scripts = $composer->getPackage()->getScripts();

        if (!\array_keys($scripts, 'cs-fixer')) {
            $scripts['cs-fixer'] = [
                '@php vendor/terminal42/contao-build-tools/tools/ecs/vendor/bin/ecs check --config vendor/terminal42/contao-build-tools/tools/ecs/config/default.php --fix --ansi'
            ];
        }

        if (!\array_keys($scripts, 'rector')) {
            $scripts['rector'] = ['@php vendor/terminal42/contao-build-tools/tools/rector/vendor/bin/rector --config vendor/terminal42/contao-build-tools/tools/rector/config.php --ansi'];
        }

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

        $binRoots = glob(__DIR__.'/../tools/*', GLOB_ONLYDIR);
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
        if (!file_exists($namespace) && !mkdir($namespace, 0777, true) && !is_dir($namespace)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $namespace));
        }

        chdir($namespace);

        // some plugins require access to composer file e.g. Symfony Flex
        if (!file_exists(Factory::getComposerFile())) {
            file_put_contents(Factory::getComposerFile(), '{}');
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
}
