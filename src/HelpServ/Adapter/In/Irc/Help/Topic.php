<?php

declare(strict_types=1);

namespace App\HelpServ\Adapter\In\Irc\Help;

/**
 * Immutable help topic exposed to the formatter.
 */
final readonly class Topic implements HelpableCommandInterface
{
    /**
     * @param array<array{name: string, desc_key: string, help_key: string, syntax_key: string}> $subcommands
     */
    public function __construct(
        private string $name,
        private int $order,
        private string $shortDescKey,
        private string $helpKey,
        private string $syntaxKey,
        private array $subcommands = [],
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function getShortDescKey(): string
    {
        return $this->shortDescKey;
    }

    public function getSyntaxKey(): string
    {
        return $this->syntaxKey;
    }

    public function getHelpKey(): string
    {
        return $this->helpKey;
    }

    /**
     * @return array<array{name: string, desc_key: string, help_key: string, syntax_key: string}>
     */
    public function getSubCommandHelp(): array
    {
        return $this->subcommands;
    }

    /**
     * @return array<int, Topic>
     */
    public static function all(): array
    {
        return [
            new self('UMODES', 1, 'topic.umodes.short', 'topic.umodes.help', 'topic.umodes.syntax'),
            new self('CMODES', 2, 'topic.cmodes.short', 'topic.cmodes.help', 'topic.cmodes.syntax'),
            new self('BOTS', 3, 'topic.bots.short', 'topic.bots.help', 'topic.bots.syntax'),
            new self('NICK', 4, 'topic.nick.short', 'topic.nick.help', 'topic.nick.syntax'),
            new self('CANAL', 5, 'topic.canal.short', 'topic.canal.help', 'topic.canal.syntax'),
            new self('CREDITOS', 6, 'topic.creditos.short', 'topic.creditos.help', 'topic.creditos.syntax'),
        ];
    }

    public static function fromName(string $name): ?self
    {
        foreach (self::all() as $topic) {
            if ($topic->getName() === strtoupper($name)) {
                return $topic;
            }
        }

        return null;
    }
}
