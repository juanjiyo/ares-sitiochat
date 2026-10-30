<?php

declare(strict_types=1);

namespace App\Tests\DebugServ\Adapter\In\Irc;

use App\DebugServ\Adapter\In\Irc\NetworkEventTranslator;
use App\Irc\Adapter\Protocol\IRCMessage;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\SenderView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NetworkEventTranslator::class)]
final class NetworkEventTranslatorTest extends TestCase
{
    private const string C = "\x03";

    private const string B = "\x0312";

    private const string R = "\x034";

    private const string O = "\x0F";

    private NetworkEventTranslator $translator;

    protected function setUp(): void
    {
        $users = $this->createStub(NetworkUserLookupPort::class);
        $users->method('findByUid')->willReturnCallback(static fn (string $uid): ?SenderView => match ($uid) {
            '001AAAAAA' => new SenderView('001AAAAAA', 'Pepe', 'pepe', 'host', 'cloak', 'ip'),
            '001BBBBBB' => new SenderView('001BBBBBB', 'Ana', 'ana', 'host', 'cloak', 'ip'),
            default => null,
        });

        $this->translator = new NetworkEventTranslator($users);
        // Learn the uplink and one remote server, as the burst would.
        $this->line('PROTOCTL NOQUIT SID=001');
        $this->line('SERVER irc.sitiochat.org 1 :SitioChat Hub');
        $this->line(':001 SID leaf.sitiochat.org 2 002 :Leaf');
    }

    /** @return array{0: string, 1: string}|null */
    private function line(string $raw, int $now = 1000): ?array
    {
        return $this->translator->translate(IRCMessage::fromRawLine($raw), $now);
    }

    private function text(string $raw, int $now = 1000): ?string
    {
        return $this->line($raw, $now)[1] ?? null;
    }

    /** @return iterable<string, array{string, string}> */
    public static function sentences(): iterable
    {
        $c = self::C;
        $b = self::B;
        $r = self::R;
        $o = self::O;

        yield 'connect' => [
            ':001 UID Luis 0 1700000000 luis 1.2.3.4 001CCCCCC 0 +ix vhost cloak AQIDBA== :Luis Real',
            "\x033Conecta{$c}:{$b} NICK{$c}: Luis {$o}{$b}IDENT{$c}: luis {$o}{$b}IP/HOST{$c}: 1.2.3.4 {$o}{$b}REALNAME{$c}: Luis Real{$o} {$b}MODOS{$c}: +ix {$b}SERVIDOR{$c}: irc.sitiochat.org",
        ];
        yield 'connect without modes' => [
            ':002 UID Luis 0 1700000000 luis 1.2.3.4 002CCCCCC 0 + vhost cloak AQIDBA== :Luis',
            "\x033Conecta{$c}:{$b} NICK{$c}: Luis {$o}{$b}IDENT{$c}: luis {$o}{$b}IP/HOST{$c}: 1.2.3.4 {$o}{$b}REALNAME{$c}: Luis{$o} {$b}SERVIDOR{$c}: leaf.sitiochat.org",
        ];
        yield 'quit' => [':001AAAAAA QUIT :Me voy', "{$r}Desconecta{$c}:{$b} NICK{$c}: Pepe {$b}({$o} Me voy {$b})"];
        yield 'nick change' => [':001AAAAAA NICK Pepito 1700000001', "{$b}Pepe {$c}cambia de nick a{$b} Pepito"];
        yield 'sjoin one' => [':001 SJOIN 1700000000 #Zamora :@001AAAAAA', "{$b}Pepe {$c}entra al canal{$b} #Zamora"];
        yield 'sjoin many skips list modes' => [
            ':001 SJOIN 1700000000 #Zamora +nt :&*!*@bad "*!*@ok \'*!*@inv +001AAAAAA ~001BBBBBB',
            "{$b}Pepe, Ana {$c}entran al canal{$b} #Zamora",
        ];
        yield 'join' => [':001AAAAAA JOIN #a', "{$b}Pepe {$c}entra al canal{$b} #a"];
        yield 'join many' => [':001AAAAAA JOIN #a,#b', "{$b}Pepe {$c}entra a los canales{$b} #a,#b"];
        yield 'part' => [':001AAAAAA PART #a', "{$b}Pepe {$c}sale del canal{$b} #a"];
        yield 'part with message' => [':001AAAAAA PART #a :adios', "{$b}Pepe {$c}sale del canal{$b} #a {$c}con el mensaje: adios"];
        yield 'part many with message' => [':001AAAAAA PART #a,#b :adios', "{$b}Pepe {$c}sale de los canales{$b} #a,#b {$c}con el mensaje: adios"];
        yield 'channel mode by user' => [':001AAAAAA MODE #a +o 001BBBBBB', "{$b}Pepe {$c}cambia modos de{$b} #a {$c}a{$b} +o Ana"];
        yield 'channel mode by server drops ts' => [':001 MODE #a +l 50 1700000000', "{$b}irc.sitiochat.org {$c}cambia modos de{$b} #a {$c}a{$b} +l 50"];
        yield 'user mode' => [':001AAAAAA MODE Pepe :+x', "{$b}Pepe {$c}cambia modos a:{$b} +x"];
        yield 'umode2' => [':001AAAAAA UMODE2 +iw', "{$b}Pepe {$c}cambia modos a{$b} +iw"];
        yield 'svs2mode' => [':001 SVS2MODE 001AAAAAA +r', "{$r}Modos forzados{$c} en{$b} Pepe{$c}:{$b} +r"];
        yield 'svsmode' => [':001 SVSMODE 001AAAAAA :-x', "{$r}Modos forzados{$c} en{$b} Pepe{$c}:{$b} -x"];
        yield 'topic by user' => [':001AAAAAA TOPIC #a Pepe 1700000000 :Hola', "{$b}Pepe {$c}cambia el topic de{$b} #a {$c}por: Hola"];
        yield 'topic cleared' => [':001AAAAAA TOPIC #a Pepe 1700000000 :', "{$b}Pepe {$c}quita el topic de{$b} #a"];
        yield 'topic by server' => [':001 TOPIC #a Pepe 1700000000 :Hola', "{$b}TOPIC{$c} del canal{$b} #a{$c}: Hola"];
        yield 'kick' => [':001AAAAAA KICK #a 001BBBBBB :fuera', "{$b}Pepe {$c}expulsa a{$b} Ana {$c}del canal{$b} #a{$c}, motivo: fuera"];
        yield 'kill' => [':001AAAAAA KILL 001BBBBBB :(abuso)', "{$b}Pepe {$r}KILL{$c}ea a{$b} Ana{$c}: abuso"];
        yield 'server' => [':001 SID new.sitiochat.org 2 003 :Nuevo', "Servidor{$b} new.sitiochat.org \x036({$c}Nuevo\x036){$c} entra en la red."];
        yield 'squit' => ['SQUIT 002 :Ping timeout', "Servidor{$b} leaf.sitiochat.org {$c}sale de la red: Ping timeout"];
        yield 'sethost' => [':001AAAAAA SETHOST bonito.vhost', "Cambio de {$b}VHOST{$c} sobre{$b} Pepe{$c}: bonito.vhost"];
        yield 'chghost' => [':001AAAAAA CHGHOST 001BBBBBB nuevo.host', "{$b}Pepe {$c}usa {$b}CHGHOST{$c} sobre{$b} Ana{$c}: nuevo.host"];
        yield 'chgident' => [':001AAAAAA CHGIDENT 001BBBBBB ident', "{$b}Pepe {$c}usa {$b}CHGIDENT{$c} sobre{$b} Ana{$c}: ident"];
        yield 'chgname' => [':001AAAAAA CHGNAME 001BBBBBB :Nombre Real', "{$b}Pepe {$c}usa {$b}CHGNAME{$c} sobre{$b} Ana{$c}: Nombre Real"];
        yield 'setname' => [':001AAAAAA SETNAME :Otro Nombre', "{$b}Pepe {$c}usa{$b} SETNAME{$c}: Otro Nombre"];
        yield 'swhois add' => [':001 SWHOIS 001AAAAAA + oper 0 :es IRCop', "{$b}irc.sitiochat.org {$c}pone un {$b}SWHOIS{$c} a{$b} Pepe{$c}: es IRCop"];
        yield 'swhois remove' => [':001 SWHOIS 001AAAAAA - oper 0 :es IRCop', "{$b}irc.sitiochat.org {$r}quita{$c} el {$b}SWHOIS{$c} a{$b} Pepe"];
        yield 'slog oper up' => [
            ':001 SLOG info oper OPER_SUCCESS :Pepe!pepe@host is now an IRC Operator [oper-block: pepe] [operclass: netadmin]',
            "{$b}Pepe {$c}es ahora{$b} {$c}({$r}netadmin{$c})",
        ];
        yield 'slog failed oper' => [
            ':001 SLOG warn oper OPER_FAILED :Failed OPER attempt by Pepe!pepe@host [reason: Password incorrect] [oper-block: pepe]',
            "{$r}Pepe {$c}intentó identificarse como IRCop...",
        ];
        yield 'slog tempshun added' => [
            ':001 SLOG info tkl TKL_ADD_TEMPSHUN :Temporary shun added on user Pepe!pepe@host [reason: flood] [by: Ana]',
            "{$r}TEMPSHUN{$c} puesto en{$b} Pepe!pepe@host {$c}por{$b} Ana{$c}: flood",
        ];
        yield 'slog tempshun removed' => [
            ':001 SLOG info tkl TKL_DEL_TEMPSHUN :Temporary shun removed from user Pepe!pepe@host [by: Ana]',
            "Eliminando{$r} TEMPSHUN {$c}puesto a{$b} Pepe!pepe@host {$c}por\x033 Ana",
        ];
        yield 'slog spamfilter with text' => [
            ":001 SLOG info tkl SPAMFILTER_MATCH :[Spamfilter] Pepe!pepe@host matches filter 'casino': [cmd: PRIVMSG #a: 'ven al Casino ya'] [reason: spam] [action: block]",
            "{$r}SPAM{$c} detectado desde{$b} Pepe{$c}: ven al {$r}casino{$c} ya",
        ];
        yield 'slog spamfilter without text' => [
            ":001 SLOG info tkl SPAMFILTER_MATCH :[Spamfilter] Pepe!pepe@host matches filter '': [cmd: JOIN #a] [reason: spam] [action: block]",
            "{$r}SPAM{$c} detectado desde{$b} Pepe{$c}: JOIN #a",
        ];
        yield 'slog other event' => [
            ':002 SLOG info link LINK_DISCONNECTED :Lost server link to x.org',
            "{$b}leaf.sitiochat.org {$c}Lost server link to x.org",
        ];
        yield 'slog known event in an unexpected shape' => [
            ':001 SLOG info oper OPER_SUCCESS :algo raro',
            "{$b}irc.sitiochat.org {$c}algo raro",
        ];
        yield 'sendsno is no longer special' => [':001 SENDSNO o :hola', '<><><> 001 SENDSNO o :hola'];
        yield 'unknown is dumped raw' => [':001 FOO bar :baz', '<><><> 001 FOO bar :baz'];
        yield 'raw dump hides credentials' => [
            ':001 FOO N::Pepe::pass :secreto AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA==',
            '<><><> 001 FOO N::Pepe::pass :<oculto> <oculto>',
        ];
    }

    #[Test]
    #[DataProvider('sentences')]
    public function translatesLikeTheOriginal(string $raw, string $expected): void
    {
        self::assertSame($expected, $this->text($raw));
    }

    #[Test]
    public function reportsTheEventType(): void
    {
        self::assertSame('QUIT', $this->line(':001AAAAAA QUIT :x')[0] ?? null);
        self::assertSame(NetworkEventTranslator::RAW, $this->line(':001 FOO')[0] ?? null);
    }

    /** @return iterable<string, array{string}> */
    public static function silentLines(): iterable
    {
        yield 'privmsg' => [':001AAAAAA PRIVMSG NiCK :IDENTIFY secreto'];
        yield 'reputation sync' => [':001 REPUTATION 46.39.209.77 19'];
        yield 'typing notification' => [':0019FJV49 TAGMSG 003B00002'];
        yield 'module list' => [':001 SMOD :G:third/redirect:1.0'];
        yield 'multiline batch start' => [':0015AATIS BATCH 003E00008 +oVIwpAZCoxrnnXGG3VY5PR draft/multiline'];
        yield 'multiline batch end' => [':0015AATIS BATCH 003E00008 -oVIwpAZCoxrnnXGG3VY5PR'];
        yield 'sasl' => [':001 SASL 002 001AAAAAA C cGVwZQ=='];
        yield 'metadata' => [':001 MD client 001AAAAAA certfp :abc'];
        yield 'numeric' => [':001 372 Pepe :motd'];
        yield 'collision kill' => [':001 KILL 001AAAAAA :irc.sitiochat.org (Nick collision (?))'];
        yield 'mode without changes' => [':001 MODE #a + 1700000000'];
        yield 'mode without sign' => [':001AAAAAA MODE #a b'];
        yield 'slog without event' => [':001 SLOG info'];
        yield 'away return when not away' => [':001AAAAAA AWAY'];
        yield 'incomplete uid' => [':001 UID Luis 0'];
        yield 'nick without prefix' => ['NICK Luis'];
        yield 'nick without new nick' => [':001AAAAAA NICK'];
        yield 'sjoin without channel' => [':001 SJOIN'];
        yield 'sjoin with only bans' => [':001 SJOIN 1 #a :&*!*@x'];
        yield 'join without prefix' => ['JOIN #a'];
        yield 'part without channel' => [':001AAAAAA PART'];
        yield 'mode without target' => [':001AAAAAA MODE'];
        yield 'umode2 without modes' => [':001AAAAAA UMODE2'];
        yield 'svs2mode without modes' => [':001 SVS2MODE 001AAAAAA'];
        yield 'topic without channel' => [':001AAAAAA TOPIC'];
        yield 'kick without victim' => [':001AAAAAA KICK #a'];
        yield 'kill without victim' => [':001AAAAAA KILL'];
        yield 'server without name' => ['SERVER'];
        yield 'sid without sid' => [':001 SID name 2'];
        yield 'squit without name' => ['SQUIT'];
        yield 'sethost without prefix' => ['SETHOST vhost'];
        yield 'chghost without target' => [':001AAAAAA CHGHOST'];
        yield 'setname without prefix' => ['SETNAME :x'];
        yield 'swhois without target' => [':001 SWHOIS'];
    }

    #[Test]
    #[DataProvider('silentLines')]
    public function keepsLinesOutOfTheChannel(string $raw): void
    {
        self::assertNull($this->line($raw));
    }

    #[Test]
    public function tracksAwayLikeTheOriginal(): void
    {
        $b = self::B;
        $c = self::C;

        self::assertSame("{$b}Pepe {$c}se ausenta, mensaje:{$b} comiendo", $this->text(':001AAAAAA AWAY :comiendo', 1000));
        self::assertNull($this->text(':001AAAAAA AWAY :comiendo', 1100));
        self::assertSame("{$b}Pepe {$c}Cambia el mensaje de away:{$b} durmiendo", $this->text(':001AAAAAA AWAY :durmiendo', 1200));
        self::assertSame("{$b}Pepe {$c}deja de estar ausente tras{$b} 1 hora 1 minuto 5 segundos{$c}.", $this->text(':001AAAAAA AWAY', 1000 + 3665));
    }

    #[Test]
    public function ignoresTheChangingPartOfScriptedAwayMessages(): void
    {
        self::assertSame(self::B . 'Pepe ' . self::C . 'se ausenta, mensaje:' . self::B . ' fuera', $this->text(':001AAAAAA AWAY :[5 min] fuera'));
        self::assertNull($this->text(':001AAAAAA AWAY :[6 min] fuera'));
        self::assertStringEndsWith('comiendo', (string) $this->text(':001BBBBBB AWAY :Away © comiendo'));
        self::assertNull($this->text(':001BBBBBB AWAY :Away 2 © comiendo'));
        self::assertStringEndsWith('sin marca©', (string) $this->text(':001CCCCCC AWAY :sin marca©'));
    }

    #[Test]
    public function forgetsAwayStateOnQuitAndKill(): void
    {
        $this->line(':001AAAAAA AWAY :fuera');
        $this->line(':001AAAAAA QUIT :bye');
        self::assertNull($this->line(':001AAAAAA AWAY'));

        $this->line(':001BBBBBB AWAY :fuera');
        $this->line(':001AAAAAA KILL 001BBBBBB :(x)');
        self::assertNull($this->line(':001BBBBBB AWAY'));
    }

    #[Test]
    public function showsUnknownUidsAsTheyAre(): void
    {
        self::assertSame(self::B . '001ZZZZZZ ' . self::C . 'cambia de nick a' . self::B . ' Otro', $this->text(':001ZZZZZZ NICK Otro 1'));
    }

    #[Test]
    public function formatsDurationsLikeMirc(): void
    {
        self::assertSame('0 segundos', NetworkEventTranslator::duration(0));
        self::assertSame('0 segundos', NetworkEventTranslator::duration(-5));
        self::assertSame('2 semanas 1 dia 1 segundo', NetworkEventTranslator::duration(2 * 604800 + 86400 + 1));
    }
}
