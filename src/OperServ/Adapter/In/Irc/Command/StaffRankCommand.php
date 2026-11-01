<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\OperServCommandInterface;
use App\OperServ\Adapter\In\Irc\OperServContext;
use App\OperServ\Application\Port\In\OperatorAuthorizationAttribute;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\UseCase\ManageStaff\ManageStaff;
use App\OperServ\Application\UseCase\ManageStaff\ManageStaffHandler;
use App\OperServ\Application\UseCase\ManageStaff\ManageStaffOutcome;
use App\OperServ\Application\UseCase\ManageStaff\ManageStaffResult;
use App\OperServ\Application\UseCase\ManageStaff\StaffAction;
use App\OperServ\Domain\ValueObject\StaffRank;
use DateTimeImmutable;

use function count;
use function sprintf;
use function strtolower;
use function strtoupper;
use function trim;

/**
 * PREOPER | OPER | ADMIN | ROOT {ADD|DEL|LIST} [nick]: one command per staff
 * rank, as in the dbots CeNTeR module. PREOPER and OPER are managed with
 * operserv.staff (ADMIN rank), ADMIN with operserv.staff.admin (ROOT rank) and
 * ROOT only by the configured main Root.
 */
final readonly class StaffRankCommand implements OperServCommandInterface
{
    public function __construct(
        private StaffRank $rank,
        private ManageStaffHandler $handler,
    ) {}

    public function getName(): string
    {
        return $this->rank->value;
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getMinArgs(): int
    {
        return 1;
    }

    public function getSyntaxKey(): string
    {
        return $this->key('syntax');
    }

    public function getHelpKey(): string
    {
        return $this->key('help');
    }

    public function getExampleKey(): string
    {
        return $this->key('example');
    }

    public function getOrder(): int
    {
        return match ($this->rank) {
            StaffRank::PREOPER => 1,
            StaffRank::OPER => 2,
            StaffRank::ADMIN => 3,
            StaffRank::ROOT => 4,
        };
    }

    public function getShortDescKey(): string
    {
        return $this->key('short');
    }

    public function getSubCommandHelp(): array
    {
        return [];
    }

    public function isOperOnly(): bool
    {
        return true;
    }

    public function getRequiredPermission(): string
    {
        return match ($this->rank) {
            StaffRank::PREOPER, StaffRank::OPER => OperServPermission::STAFF,
            StaffRank::ADMIN => OperServPermission::STAFF_ADMIN,
            StaffRank::ROOT => OperatorAuthorizationAttribute::ROOT,
        };
    }

    /** @return array<string, string> */
    public function getHelpParams(): array
    {
        return [];
    }

    public function execute(OperServContext $context): void
    {
        if (null === $context->sender) {
            return;
        }

        $sub = strtoupper($context->args[0] ?? '');
        $action = match ($sub) {
            'ADD' => StaffAction::Add,
            'DEL' => StaffAction::Delete,
            'LIST' => StaffAction::List,
            default => StaffAction::Unknown,
        };

        $result = $this->handler->handle(new ManageStaff(
            $this->rank,
            $action,
            $context->sender->nick,
            $context->senderAccountId(),
            new DateTimeImmutable(),
            trim($context->args[1] ?? ''),
        ));

        $this->present($context, $result);
    }

    private function present(OperServContext $context, ManageStaffResult $result): void
    {
        $nick = ['%nickname%' => $result->nickname];
        match ($result->outcome) {
            ManageStaffOutcome::Added => $context->reply('staff.added', $nick + ['%rank%' => $this->title($context, $this->rank)]),
            ManageStaffOutcome::Deleted => $context->reply('staff.deleted', $nick + ['%rank%' => $this->title($context, $this->rank)]),
            ManageStaffOutcome::AlreadyStaff => null === $result->currentRank
                ? $context->reply('staff.already_staff', $nick)
                : $context->reply('staff.already_rank', $nick + ['%rank%' => $this->title($context, $result->currentRank)]),
            ManageStaffOutcome::NotThisRank => $context->reply('staff.not_this_rank', $nick + ['%rank%' => $this->title($context, $this->rank)]),
            ManageStaffOutcome::NickNotRegistered => $context->reply('error.nick_not_registered', $nick),
            ManageStaffOutcome::NickNotActive => $context->reply('staff.nick_not_active', $nick),
            ManageStaffOutcome::RankUnavailable => $context->reply('staff.unavailable'),
            ManageStaffOutcome::InvalidRequest, ManageStaffOutcome::UnknownAction => $context->reply('error.syntax', ['%syntax%' => $context->trans($this->getSyntaxKey())]),
            ManageStaffOutcome::Listed => $this->presentList($context, $result),
        };
    }

    private function presentList(OperServContext $context, ManageStaffResult $result): void
    {
        $title = $this->title($context, $this->rank);
        if ([] === $result->entries) {
            $context->reply('staff.list.empty', ['%rank%' => $title]);

            return;
        }

        $context->reply('staff.list.header', ['%rank%' => $title, '%count%' => (string) count($result->entries)]);
        foreach ($result->entries as $entry) {
            $context->replyRaw(sprintf('  %-20s %s', $entry->nickname, $context->formatDate($entry->addedAt)));
        }
    }

    private function title(OperServContext $context, StaffRank $rank): string
    {
        return $context->trans('staff.title.' . strtolower($rank->value));
    }

    private function key(string $suffix): string
    {
        return 'staff.' . strtolower($this->rank->value) . '.' . $suffix;
    }
}
