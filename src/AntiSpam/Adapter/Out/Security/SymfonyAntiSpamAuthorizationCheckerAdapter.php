<?php

declare(strict_types=1);

namespace App\AntiSpam\Adapter\Out\Security;

use App\AntiSpam\Adapter\In\Irc\AntiSpamAuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface as SymfonyAuthorizationCheckerInterface;

final readonly class SymfonyAntiSpamAuthorizationCheckerAdapter implements AntiSpamAuthorizationCheckerInterface
{
    public function __construct(
        private SymfonyAuthorizationCheckerInterface $authorizationChecker,
    ) {}

    public function isGranted(string $permission, mixed $subject = null): bool
    {
        return $this->authorizationChecker->isGranted($permission, $subject);
    }
}
