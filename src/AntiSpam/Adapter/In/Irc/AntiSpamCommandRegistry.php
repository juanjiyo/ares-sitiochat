<?php

declare(strict_types=1);

namespace App\AntiSpam\Adapter\In\Irc;

use function spl_object_id;
use function strtoupper;

/**
 * Holds all registered AntiSpam command modules (tag: antispam.command).
 */
final readonly class AntiSpamCommandRegistry
{
    /** @var array<string, AntiSpamCommandInterface> keyed by uppercase name/alias */
    private array $map;

    /**
     * @param iterable<AntiSpamCommandInterface> $commands
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

    public function find(string $name): ?AntiSpamCommandInterface
    {
        return $this->map[strtoupper($name)] ?? null;
    }

    /** @return AntiSpamCommandInterface[] unique command instances */
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
