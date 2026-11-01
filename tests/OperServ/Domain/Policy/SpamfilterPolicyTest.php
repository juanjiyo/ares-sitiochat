<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Domain\Policy;

use App\OperServ\Domain\Policy\SpamfilterPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(SpamfilterPolicy::class)]
final class SpamfilterPolicyTest extends TestCase
{
    #[Test]
    public function cannotBeInstantiated(): void
    {
        $reflection = new ReflectionClass(SpamfilterPolicy::class);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
        $constructor->invoke($reflection->newInstanceWithoutConstructor());
    }

    #[Test]
    public function mapsActionNamesToIrcdLettersAndBack(): void
    {
        self::assertSame(['warn', 'block', 'kill', 'tempshun', 'shun', 'kline', 'gline', 'zline', 'gzline', 'dccblock', 'viruschan'], SpamfilterPolicy::actionNames());
        self::assertSame('g', SpamfilterPolicy::actionLetter('GLINE'));
        self::assertSame('w', SpamfilterPolicy::actionLetter('warn'));
        self::assertSame('K', SpamfilterPolicy::actionLetter('kill'));
        self::assertNull(SpamfilterPolicy::actionLetter('nuke'));
        self::assertSame('gline', SpamfilterPolicy::actionName('g'));
        self::assertSame('gzline', SpamfilterPolicy::actionName('Z'));
        self::assertSame('G', SpamfilterPolicy::actionName('G'));
    }

    #[Test]
    public function onlyTimedBanActionsUseTheDuration(): void
    {
        foreach (['s', 'k', 'g', 'z', 'Z'] as $letter) {
            self::assertTrue(SpamfilterPolicy::isTimedBan($letter), $letter);
        }
        foreach (['w', 'b', 'K', 'S', 'd', 'v', ''] as $letter) {
            self::assertFalse(SpamfilterPolicy::isTimedBan($letter), $letter);
        }
    }

    #[Test]
    public function normalizesTargetsToTheIrcdOrderWithoutRepeats(): void
    {
        self::assertSame('cpnNPqat', SpamfilterPolicy::targets('all'));
        self::assertSame('cpN', SpamfilterPolicy::targets('Npcp'));
        self::assertSame('cpnNPqdat', SpamfilterPolicy::targets('tadqPNnpc'));
    }

    #[Test]
    public function rejectsEmptyOrUnknownTargets(): void
    {
        self::assertNull(SpamfilterPolicy::targets(''));
        self::assertNull(SpamfilterPolicy::targets('cx'));
        self::assertNull(SpamfilterPolicy::targets('C'));
    }
}
