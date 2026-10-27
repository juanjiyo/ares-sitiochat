<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\Out\Security;

use App\GlobalServ\Adapter\In\Irc\GlobalServAuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface as SymfonyAuthorizationCheckerInterface;

final readonly class SymfonyGlobalServAuthorizationCheckerAdapter implements GlobalServAuthorizationCheckerInterface
{
    public function __construct(
        private SymfonyAuthorizationCheckerInterface $authorizationChecker,
    ) {}

    public function isGranted(string $permission, mixed $subject = null): bool
    {
        return $this->authorizationChecker->isGranted($permission, $subject);
    }
}
