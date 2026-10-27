<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\Out\Security;

use App\GlobalServ\Adapter\In\Irc\GlobalServAuthorizationContextInterface;
use App\NickServ\Application\Port\In\IrcAuthorizationContext;

final readonly class GlobalServAuthorizationContextAdapter implements GlobalServAuthorizationContextInterface
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
