<?php

declare(strict_types=1);

namespace Terminal42\ContaoBuildTools\Composer;

use Composer\Command\ScriptAliasCommand;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;

class CommandProvider implements CommandProviderCapability
{
    private Plugin $plugin;

    public function __construct(array $config)
    {
        $this->plugin = $config['plugin'];
    }

    public function getCommands(): array
    {
        $commands = [];

        foreach ($this->plugin->activatedScripts as $script => $description) {
            $commands[] = new ScriptAliasCommand($script, $description);
        }

        return $commands;
    }
}
