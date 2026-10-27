<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\In\Irc;

use App\Irc\Application\Port\In\SenderView;
use App\Irc\Application\Port\In\ServiceNicknameRegistry;
use App\OperServ\Application\Port\In\IrcopAuthorizationSubject;
use Symfony\Contracts\Translation\TranslatorInterface;

use function trim;

/**
 * Presentation context for GlobalServ command adapters. Implements the shared
 * Ircop authorization subject contract so the centralized voters can run.
 */
final readonly class GlobalServContext implements IrcopAuthorizationSubject
{
    /**
     * @param string[] $args
     */
    public function __construct(
        public ?SenderView $sender,
        public ?int $senderAccountId,
        public string $command,
        public array $args,
        private GlobalServNotifierInterface $notifier,
        private TranslatorInterface $translator,
        private string $language,
        private GlobalServCommandRegistry $registry,
        private ServiceNicknameRegistry $serviceNicks,
    ) {}

    public function getSender(): ?SenderView
    {
        return $this->sender;
    }

    public function getSenderAccountId(): ?int
    {
        return $this->senderAccountId;
    }

    public function getSenderNickname(): ?string
    {
        return $this->sender?->nick;
    }

    public function isSenderIdentified(): bool
    {
        return null !== $this->sender && $this->sender->isIdentified;
    }

    public function isSenderIrcOperator(): bool
    {
        return null !== $this->sender && $this->sender->isOper;
    }

    public function getRegistry(): GlobalServCommandRegistry
    {
        return $this->registry;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function reply(string $key, array $params = []): void
    {
        $message = $this->translator->trans($key, $this->wrapParams($params), 'globalserv', $this->language);
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
        return $this->translator->trans($key, $this->wrapParams($params), 'globalserv', $this->language);
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
