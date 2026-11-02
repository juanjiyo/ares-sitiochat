<?php

declare(strict_types=1);

namespace App\NickServ\Application\UseCase\AutoMode;

use App\NickServ\Application\Port\Out\NickServEventPublisher;
use App\NickServ\Application\Port\Out\RegisteredNickRepositoryInterface;
use App\NickServ\Application\PublishedEvent\NickAutoModesChangedEvent;
use App\NickServ\Application\Service\NickProtectabilityStatus;
use App\NickServ\Application\Service\NickTargetValidator;

use function array_diff;
use function array_unique;
use function implode;
use function ltrim;
use function preg_match;
use function str_split;
use function trim;

/**
 * AUTOMODE: stores the user modes the IRCd applies every time the nick identifies (UDB N::<nick>::modes),
 * like dbots' NiCK AUTOMODE. Only adding modes is supported; an empty value clears them.
 */
final readonly class SetAutoModesHandler implements SetAutoModesHandlerInterface
{
    /** Profile user modes accepted by the UDB module in N::<nick>::modes ('o' is never allowed). */
    public const array ALLOWED_MODES = ['i', 's', 'w', 'B', 'S', 'T', 'G', 'W', 'p', 'q', 'R', 'Z', 'D'];

    public function __construct(
        private RegisteredNickRepositoryInterface $nicks,
        private NickTargetValidator $targetValidator,
        private NickServEventPublisher $events,
    ) {}

    public function handle(SetAutoModes $command): SetAutoModesResult
    {
        $target = $this->targetValidator->validate($command->targetNickname);
        if (!$target->isAllowed()) {
            $outcome = match ($target->status) {
                NickProtectabilityStatus::IsRoot => SetAutoModesOutcome::TargetIsRoot,
                NickProtectabilityStatus::IsIrcop => SetAutoModesOutcome::TargetIsIrcop,
                default => SetAutoModesOutcome::TargetIsService,
            };

            return new SetAutoModesResult($outcome, $command->targetNickname);
        }

        $account = $this->nicks->findByNick($command->targetNickname);
        if (null === $account || $account->isForbidden() || $account->isPending()) {
            return new SetAutoModesResult(SetAutoModesOutcome::NotRegistered, $command->targetNickname);
        }

        $nickname = $account->getNickname();
        $input = trim($command->modes);
        $modes = null;
        if ('' !== $input) {
            if (1 !== preg_match('/^\+?[A-Za-z]+$/', $input)) {
                return new SetAutoModesResult(SetAutoModesOutcome::InvalidModes, $nickname, invalidModes: $input);
            }

            $letters = array_unique(str_split(ltrim($input, '+')));
            $invalid = array_diff($letters, self::ALLOWED_MODES);
            if ([] !== $invalid) {
                return new SetAutoModesResult(SetAutoModesOutcome::InvalidModes, $nickname, invalidModes: implode('', $invalid));
            }

            $modes = '+' . implode('', $letters);
        }

        $account->changeAutoModes($modes);
        $this->nicks->save($account);
        $this->events->publish(new NickAutoModesChangedEvent($account->getId(), $nickname, $modes));

        return null === $modes
            ? new SetAutoModesResult(SetAutoModesOutcome::Cleared, $nickname)
            : new SetAutoModesResult(SetAutoModesOutcome::Set, $nickname, $modes);
    }
}
