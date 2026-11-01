<?php

declare(strict_types=1);

namespace App\AntiSpam\Adapter\Out\Security;

use App\AntiSpam\Adapter\In\Irc\AntiSpamAuthorizationContextInterface;
use App\NickServ\Application\Port\In\IrcAuthorizationContext;

final readonly class AntiSpamAuthorizationContextAdapter implements AntiSpamAuthorizationContextInterface
{
    public function __construct(
        private IrcAuthorizationContext $authorizationContext,
    ) {}

    public function setCurrentUser(string $uid, bool $isIdentified, bool $isOper): void
    {
        $this->authorizationContext->setCurrentUser($uid, $isIdentified, $isOper);
    }

    public function clear(): void
    {
        $this->authorizationContext->clear();
    }
}
