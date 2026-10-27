<?php

declare(strict_types=1);

namespace App\HelpServ\Adapter\In\Irc;

use function spl_object_id;
use function strtoupper;

/**
 * Holds all registered HeLPServ command modules (tag: helpserv.command).
 */
final readonly class HelpServCommandRegistry
{
    /** @var array<string, HelpServCommandInterface> keyed by uppercase name/alias */
    private array $map;

    /**
     * @param iterable<HelpServCommandInterface> $commands
     */
    public function __construct(iterable $commands)
    {
        $map = [];
        foreach ($commands as $command) {
            $map[strtoupper($command->getName())] = $command;
            foreach ($command->getAliases() as $alias) {
                $map[strtoupper($alias)] = $command;
            }
        }
        $this->map = $map;
    }

    public function find(string $name): ?HelpServCommandInterface
    {
        return $this->map[strtoupper($name)] ?? null;
    }

    /** @return HelpServCommandInterface[] unique command instances */
    public function all(): array
    {
        $seen = [];
        $unique = [];

        foreach ($this->map as $command) {
            $id = spl_object_id($command);
            if (!isset($seen[$id])) {
                $seen[$id] = true;
                $unique[] = $command;
            }
        }

        return $unique;
    }
}
