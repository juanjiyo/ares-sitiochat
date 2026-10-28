<?php

declare(strict_types=1);

namespace App\HelpServ\Adapter\In\Irc;

use App\Irc\Application\Port\In\SenderView;
use App\Irc\Application\Port\In\ServiceNicknameRegistry;
use Symfony\Contracts\Translation\TranslatorInterface;

use function trim;

final readonly class HelpServContext
{
    /**
     * @param string[] $args
     */
    public function __construct(
        public ?SenderView $sender,
        public string $command,
        public array $args,
        private HelpServNotifierInterface $notifier,
        private TranslatorInterface $translator,
        private string $language,
        private HelpServCommandRegistry $registry,
        private ServiceNicknameRegistry $serviceNicks,
    ) {}

    public function getSender(): ?SenderView
    {
        return $this->sender;
    }

    public function getRegistry(): HelpServCommandRegistry
    {
        return $this->registry;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function reply(string $key, array $params = []): void
    {
        $message = $this->translator->trans($key, $this->wrapParams($params), 'helpserv', $this->language);
        $this->sendRaw($message);
    }

    public function replyRaw(string $message): void
    {
        $this->sendRaw($message);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function trans(string $key, array $params = []): string
    {
        return $this->translator->trans($key, $this->wrapParams($params), 'helpserv', $this->language);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function wrapParams(array $params): array
    {
        $wrapped = $this->serviceNicks->getAllPlaceholders($this->notifier->getNick());
        foreach ($params as $key => $value) {
            $wrapped['%' . trim((string) $key, '%') . '%'] = $value;
        }

        return $wrapped;
    }

    private function sendRaw(string $message): void
    {
        if (null === $this->sender) {
            return;
        }

        $this->notifier->sendMessage($this->sender->uid, $message, 'NOTICE');
    }
}
