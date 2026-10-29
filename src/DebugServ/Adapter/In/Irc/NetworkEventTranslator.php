<?php

declare(strict_types=1);

namespace App\DebugServ\Adapter\In\Irc;

use App\Irc\Adapter\Protocol\IRCMessage;
use App\Irc\Application\Port\In\NetworkUserLookupPort;

use function array_key_last;
use function array_map;
use function array_search;
use function array_slice;
use function array_values;
use function count;
use function ctype_digit;
use function explode;
use function implode;
use function in_array;
use function intdiv;
use function ltrim;
use function max;
use function preg_match;
use function preg_replace;
use function sprintf;
use function str_contains;
use function str_ireplace;
use function str_replace;
use function str_starts_with;
use function strpos;
use function substr;
use function trim;

/**
 * Turns one UnrealIRCd 6 server-to-server line into the sentence the debug
 * channel shows.
 *
 * Port of the branch table in the original `de.mrc` (via dbots-services): the
 * same events, the same Spanish wording and the same mIRC colour codes. The
 * original spoke the old nick-based protocol; here UIDs and SIDs are resolved
 * back to nicks and server names so the channel reads the same.
 *
 * It keeps the little state the original kept: who is away and since when, and
 * which SID belongs to which server.
 */
final class NetworkEventTranslator
{
    public const string RAW = 'RAW';

    private const string C = "\x03";

    private const string BLUE = "\x0312";

    private const string RED = "\x034";

    private const string GREEN = "\x033";

    private const string PURPLE = "\x036";

    private const string O = "\x0F";

    /** Lines the original deliberately kept out of the channel. */
    private const array IGNORED = [
        'PRIVMSG', 'NOTICE', 'TAGMSG', 'PING', 'PONG', 'WHOIS', 'PROTOCTL', 'ADMIN', 'INFO', 'VERSION', 'PASS',
        'SMO', 'EOS', 'TKL', 'GLOBOPS', 'DBQ', 'DB', 'NETINFO',
        // UnrealIRCd 6 additions: metadata, server info, GLOBOPS' new name, and
        // SASL, whose payload is a credential.
        'MD', 'SINFO', 'SENDUMODE', 'SASL', 'SVSLOGIN', 'ERROR', 'CAPAB',
    ];

    /** @var array<string, string> SID => server name */
    private array $servers = [];

    private ?string $uplinkSid = null;

    /** @var array<string, array{message: string, since: int}> UID => away state */
    private array $away = [];

    public function __construct(
        private readonly NetworkUserLookupPort $users,
    ) {}

    /**
     * @return array{0: string, 1: string}|null [event type, sentence], or null when
     *                                          the line is not shown
     */
    public function translate(IRCMessage $message, int $now): ?array
    {
        $command = $message->command;

        if (in_array($command, self::IGNORED, true) || 1 === preg_match('/^\d{3}$/', $command)) {
            if ('PROTOCTL' === $command) {
                $this->rememberUplinkSid($message);
            }

            return null;
        }

        $text = match ($command) {
            'UID' => $this->connect($message),
            'QUIT' => $this->quit($message),
            'NICK' => $this->nickChange($message),
            'SJOIN' => $this->sjoin($message),
            'JOIN' => $this->join($message),
            'PART' => $this->part($message),
            'MODE' => $this->mode($message),
            'UMODE2' => $this->umode2($message),
            'SVS2MODE', 'SVSMODE' => $this->forcedModes($message),
            'TOPIC' => $this->topic($message),
            'KICK' => $this->kick($message),
            'KILL' => $this->kill($message),
            'AWAY' => $this->awayChange($message, $now),
            'SERVER' => $this->server($message),
            'SID' => $this->sid($message),
            'SQUIT' => $this->squit($message),
            'SETHOST' => $this->sethost($message),
            'CHGHOST', 'CHGIDENT' => $this->chg($message, (string) ($message->params[1] ?? '')),
            'CHGNAME' => $this->chg($message, (string) $message->trailing),
            'SETNAME' => $this->setname($message),
            'SWHOIS' => $this->swhois($message),
            'SLOG' => $this->slog($message),
            default => false,
        };

        if (false === $text) {
            return [self::RAW, '<><><> ' . $this->redact(ltrim($message->toRawLine(), ':'))];
        }

        return null === $text ? null : [$command, $text];
    }

    private function connect(IRCMessage $message): ?string
    {
        // UID nick hop ts ident host uid servicestamp umodes vhost cloak ip :gecos
        $p = $message->params;
        if (count($p) < 8) {
            return null;
        }

        $modes = $p[7];

        return sprintf(
            '%sConecta%s:%s NICK%s: %s %s%sIDENT%s: %s %s%sIP/HOST%s: %s %s%sREALNAME%s: %s%s %s%sSERVIDOR%s: %s',
            self::GREEN,
            self::C,
            self::BLUE,
            self::C,
            $p[0],
            self::O,
            self::BLUE,
            self::C,
            $p[3],
            self::O,
            self::BLUE,
            self::C,
            $p[4],
            self::O,
            self::BLUE,
            self::C,
            (string) $message->trailing,
            self::O,
            '' === ltrim($modes, '+') ? '' : sprintf('%sMODOS%s: %s ', self::BLUE, self::C, $modes),
            self::BLUE,
            self::C,
            $this->serverName((string) $message->prefix),
        );
    }

    private function quit(IRCMessage $message): string
    {
        $uid = (string) $message->prefix;
        unset($this->away[$uid]);

        return sprintf(
            '%sDesconecta%s:%s NICK%s: %s %s(%s %s %s)',
            self::RED,
            self::C,
            self::BLUE,
            self::C,
            $this->name($uid),
            self::BLUE,
            self::O,
            (string) $message->trailing,
            self::BLUE,
        );
    }

    private function nickChange(IRCMessage $message): ?string
    {
        $newNick = $message->params[0] ?? null;
        if (null === $newNick || null === $message->prefix) {
            return null;
        }

        return sprintf('%s%s %scambia de nick a%s %s', self::BLUE, $this->name($message->prefix), self::C, self::BLUE, $newNick);
    }

    /** Joins travel as SJOIN in UnrealIRCd 6: `:SID SJOIN ts #chan [modes] :members`. */
    private function sjoin(IRCMessage $message): ?string
    {
        $channel = $message->params[1] ?? null;
        if (null === $channel) {
            return null;
        }

        $names = [];
        foreach (explode(' ', (string) $message->trailing) as $member) {
            // SJ3 also carries list modes: & bans, " exempts, ' invex.
            if ('' === $member || in_array($member[0], ['&', '"', "'"], true)) {
                continue;
            }

            $uid = ltrim($member, '*~@%+');
            if ('' !== $uid) {
                $names[] = $this->name($uid);
            }
        }

        return match (count($names)) {
            0 => null,
            1 => sprintf('%s%s %sentra al canal%s %s', self::BLUE, $names[0], self::C, self::BLUE, $channel),
            default => sprintf('%s%s %sentran al canal%s %s', self::BLUE, implode(', ', $names), self::C, self::BLUE, $channel),
        };
    }

    private function join(IRCMessage $message): ?string
    {
        $channels = $message->params[0] ?? null;
        if (null === $channels || null === $message->prefix) {
            return null;
        }

        $verb = str_contains($channels, ',') ? 'entra a los canales' : 'entra al canal';

        return sprintf('%s%s %s%s%s %s', self::BLUE, $this->name($message->prefix), self::C, $verb, self::BLUE, $channels);
    }

    private function part(IRCMessage $message): ?string
    {
        $channels = $message->params[0] ?? null;
        if (null === $channels || null === $message->prefix) {
            return null;
        }

        $line = sprintf(
            '%s%s %s%s%s %s',
            self::BLUE,
            $this->name($message->prefix),
            self::C,
            str_contains($channels, ',') ? 'sale de los canales' : 'sale del canal',
            self::BLUE,
            $channels,
        );

        $reason = (string) $message->trailing;

        return '' === $reason ? $line : sprintf('%s %scon el mensaje: %s', $line, self::C, $reason);
    }

    private function mode(IRCMessage $message): ?string
    {
        $target = $message->params[0] ?? null;
        $source = (string) $message->prefix;
        if (null === $target) {
            return null;
        }

        $args = array_slice($message->params, 1);
        if (null !== $message->trailing) {
            $args[] = $message->trailing;
        }

        if (!str_starts_with($target, '#')) {
            return sprintf('%s%s %scambia modos a:%s %s', self::BLUE, $this->name($source), self::C, self::BLUE, implode(' ', $args));
        }

        $modes = $args[0] ?? '';
        if ('' === ltrim($modes, '+-') || (!str_contains($modes, '+') && !str_contains($modes, '-'))) {
            return null;
        }

        // A server appends the channel timestamp; the original dropped it.
        $last = array_key_last($args);
        if ($this->isServer($source) && 0 < $last && ctype_digit($args[$last])) {
            unset($args[$last]);
        }

        $args = array_map(fn (string $arg): string => $this->isUid($arg) ? $this->name($arg) : $arg, array_values($args));

        return sprintf(
            '%s%s %scambia modos de%s %s %sa%s %s',
            self::BLUE,
            $this->name($source),
            self::C,
            self::BLUE,
            $target,
            self::C,
            self::BLUE,
            implode(' ', $args),
        );
    }

    private function umode2(IRCMessage $message): ?string
    {
        $modes = $message->params[0] ?? $message->trailing;
        if (null === $modes || null === $message->prefix) {
            return null;
        }

        return sprintf('%s%s %scambia modos a%s %s', self::BLUE, $this->name($message->prefix), self::C, self::BLUE, $modes);
    }

    private function forcedModes(IRCMessage $message): ?string
    {
        $target = $message->params[0] ?? null;
        $modes = array_slice($message->params, 1);
        if (null !== $message->trailing) {
            $modes[] = $message->trailing;
        }

        if (null === $target || [] === $modes) {
            return null;
        }

        return sprintf('%sModos forzados%s en%s %s%s:%s %s', self::RED, self::C, self::BLUE, $this->name($target), self::C, self::BLUE, implode(' ', $modes));
    }

    /** `:source TOPIC #chan setter ts :topic`. */
    private function topic(IRCMessage $message): ?string
    {
        $channel = $message->params[0] ?? null;
        $source = (string) $message->prefix;
        if (null === $channel) {
            return null;
        }

        $topic = (string) $message->trailing;

        if ('' === $source || $this->isServer($source)) {
            return sprintf('%sTOPIC%s del canal%s %s%s: %s', self::BLUE, self::C, self::BLUE, $channel, self::C, $topic);
        }

        return '' === $topic
            ? sprintf('%s%s %squita el topic de%s %s', self::BLUE, $this->name($source), self::C, self::BLUE, $channel)
            : sprintf('%s%s %scambia el topic de%s %s %spor: %s', self::BLUE, $this->name($source), self::C, self::BLUE, $channel, self::C, $topic);
    }

    private function kick(IRCMessage $message): ?string
    {
        $channel = $message->params[0] ?? null;
        $victim = $message->params[1] ?? null;
        if (null === $channel || null === $victim) {
            return null;
        }

        return sprintf(
            '%s%s %sexpulsa a%s %s %sdel canal%s %s%s, motivo: %s',
            self::BLUE,
            $this->name((string) $message->prefix),
            self::C,
            self::BLUE,
            $this->name($victim),
            self::C,
            self::BLUE,
            $channel,
            self::C,
            (string) $message->trailing,
        );
    }

    private function kill(IRCMessage $message): ?string
    {
        $victim = $message->params[0] ?? null;
        $reason = (string) $message->trailing;

        // Nick collisions carry "(?)"; the original kept them out.
        if (null === $victim || str_contains($reason, '(?)')) {
            return null;
        }

        unset($this->away[$victim]);

        return sprintf(
            '%s%s %sKILL%sea a%s %s%s: %s',
            self::BLUE,
            $this->name((string) $message->prefix),
            self::RED,
            self::C,
            self::BLUE,
            $this->name($victim),
            self::C,
            str_replace(['(', ')'], '', $reason),
        );
    }

    private function awayChange(IRCMessage $message, int $now): ?string
    {
        $uid = (string) $message->prefix;
        $text = trim((string) ($message->trailing ?? $message->params[0] ?? ''));
        $nick = $this->name($uid);

        if ('' === $text) {
            if (!isset($this->away[$uid])) {
                return null;
            }

            $since = $this->away[$uid]['since'];
            unset($this->away[$uid]);

            return sprintf('%s%s %sdeja de estar ausente tras%s %s%s.', self::BLUE, $nick, self::C, self::BLUE, self::duration($now - $since), self::C);
        }

        $text = self::awayMessage($text);

        if (isset($this->away[$uid])) {
            if ($this->away[$uid]['message'] === $text) {
                return null;
            }

            $this->away[$uid]['message'] = $text;

            return sprintf('%s%s %sCambia el mensaje de away:%s %s', self::BLUE, $nick, self::C, self::BLUE, $text);
        }

        $this->away[$uid] = ['message' => $text, 'since' => $now];

        return sprintf('%s%s %sse ausenta, mensaje:%s %s', self::BLUE, $nick, self::C, self::BLUE, $text);
    }

    /**
     * Away scripts rewrite their message every minute ("[5 min] ..." or "... © ...");
     * the original compared only the stable part so the channel is not flooded.
     */
    private static function awayMessage(string $text): string
    {
        $tokens = explode(' ', $text);
        $mark = array_search('©', $tokens, true);
        if (str_contains($text, '©')) {
            return false === $mark ? $text : implode(' ', array_slice($tokens, $mark + 1));
        }

        if (str_starts_with($text, '[') && false !== ($close = strpos($text, ']'))
            && (str_contains($text, 'min') || str_contains($text, 'hr') || str_contains($text, 'sec'))) {
            return trim(substr($text, $close + 1));
        }

        return $text;
    }

    /** mIRC's $duration with the original's Spanish unit names: "1 hora 5 minutos 3 segundos". */
    public static function duration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $units = [[604800, 'semana'], [86400, 'dia'], [3600, 'hora'], [60, 'minuto'], [1, 'segundo']];
        $parts = [];

        foreach ($units as [$size, $name]) {
            $amount = intdiv($seconds, $size);
            $seconds -= $amount * $size;
            if (0 < $amount) {
                $parts[] = sprintf('%d %s%s', $amount, $name, 1 === $amount ? '' : 's');
            }
        }

        return [] === $parts ? '0 segundos' : implode(' ', $parts);
    }

    /** The direct uplink introduces itself with `SERVER name 1 :desc` after `PROTOCTL ... SID=xxx`. */
    private function rememberUplinkSid(IRCMessage $message): void
    {
        foreach ($message->params as $token) {
            if (str_starts_with($token, 'SID=')) {
                $this->uplinkSid = substr($token, 4);
            }
        }
    }

    private function server(IRCMessage $message): ?string
    {
        $name = $message->params[0] ?? null;
        if (null === $name) {
            return null;
        }

        if (null === $message->prefix && null !== $this->uplinkSid) {
            $this->servers[$this->uplinkSid] = $name;
        }

        return $this->serverJoined($name, (string) $message->trailing);
    }

    /** `:parent SID name hop sid :description`. */
    private function sid(IRCMessage $message): ?string
    {
        $name = $message->params[0] ?? null;
        $sid = $message->params[2] ?? null;
        if (null === $name || null === $sid) {
            return null;
        }

        $this->servers[$sid] = $name;

        return $this->serverJoined($name, (string) $message->trailing);
    }

    private function serverJoined(string $name, string $description): string
    {
        return sprintf('Servidor%s %s %s(%s%s%s)%s entra en la red.', self::BLUE, $name, self::PURPLE, self::C, $description, self::PURPLE, self::C);
    }

    private function squit(IRCMessage $message): ?string
    {
        $name = $message->params[0] ?? null;
        if (null === $name) {
            return null;
        }

        return sprintf('Servidor%s %s %ssale de la red: %s', self::BLUE, $this->serverName($name), self::C, (string) $message->trailing);
    }

    private function sethost(IRCMessage $message): ?string
    {
        $vhost = $message->params[0] ?? $message->trailing;
        if (null === $vhost || null === $message->prefix) {
            return null;
        }

        return sprintf('Cambio de %sVHOST%s sobre%s %s%s: %s', self::BLUE, self::C, self::BLUE, $this->name($message->prefix), self::C, $vhost);
    }

    private function chg(IRCMessage $message, string $value): ?string
    {
        $target = $message->params[0] ?? null;
        if (null === $target) {
            return null;
        }

        return sprintf(
            '%s%s %susa %s%s%s sobre%s %s%s: %s',
            self::BLUE,
            $this->name((string) $message->prefix),
            self::C,
            self::BLUE,
            $message->command,
            self::C,
            self::BLUE,
            $this->name($target),
            self::C,
            $value,
        );
    }

    private function setname(IRCMessage $message): ?string
    {
        if (null === $message->prefix) {
            return null;
        }

        return sprintf('%s%s %susa%s SETNAME%s: %s', self::BLUE, $this->name($message->prefix), self::C, self::BLUE, self::C, (string) $message->trailing);
    }

    /** `:source SWHOIS uid +|- tag priority :text`. */
    private function swhois(IRCMessage $message): ?string
    {
        $target = $message->params[0] ?? null;
        if (null === $target) {
            return null;
        }

        $text = trim((string) $message->trailing);
        $source = $this->name((string) $message->prefix);

        if ('-' === ($message->params[1] ?? null) || '' === $text) {
            return sprintf('%s%s %squita%s el %sSWHOIS%s a%s %s', self::BLUE, $source, self::RED, self::C, self::BLUE, self::C, self::BLUE, $this->name($target));
        }

        return sprintf('%s%s %spone un %sSWHOIS%s a%s %s%s: %s', self::BLUE, $source, self::C, self::BLUE, self::C, self::BLUE, $this->name($target), self::C, $text);
    }

    /**
     * Network-wide server notices. UnrealIRCd 6 sends them as
     * `:SID SLOG level subsystem EVENT_ID :text` (the old SENDSNO is gone); the
     * events the original singled out keep its sentences, the rest use its
     * generic "<sender> <text>" form.
     */
    private function slog(IRCMessage $message): ?string
    {
        $event = $message->params[2] ?? null;
        $text = (string) $message->trailing;
        if (null === $event) {
            return null;
        }

        $m = [];

        if ('OPER_SUCCESS' === $event
            && 1 === preg_match('/^([^!\s]+)\S* is now an IRC Operator .*\[operclass: ([^\]]*)\]/', $text, $m)) {
            return sprintf('%s%s %ses ahora%s %s(%s%s%s)', self::BLUE, $m[1], self::C, self::BLUE, self::C, self::RED, $m[2], self::C);
        }

        if ('OPER_FAILED' === $event && 1 === preg_match('/^Failed OPER attempt by ([^!\s]+)/', $text, $m)) {
            return sprintf('%s%s %sintentó identificarse como IRCop...', self::RED, $m[1], self::C);
        }

        if ('TKL_ADD_TEMPSHUN' === $event
            && 1 === preg_match('/^Temporary shun added on user (\S+) \[reason: (.*)\] \[by: ([^\]]*)\]$/', $text, $m)) {
            return sprintf('%sTEMPSHUN%s puesto en%s %s %spor%s %s%s: %s', self::RED, self::C, self::BLUE, $m[1], self::C, self::BLUE, $m[3], self::C, $m[2]);
        }

        if ('TKL_DEL_TEMPSHUN' === $event
            && 1 === preg_match('/^Temporary shun removed from user (\S+) \[by: ([^\]]*)\]$/', $text, $m)) {
            return sprintf('Eliminando%s TEMPSHUN %spuesto a%s %s %spor%s %s', self::RED, self::C, self::BLUE, $m[1], self::C, self::GREEN, $m[2]);
        }

        if ('SPAMFILTER_MATCH' === $event
            && 1 === preg_match("/^\\[Spamfilter\\] ([^!\\s]+)\\S* matches filter '(.*?)': \\[cmd: (.*)\\] \\[reason: /", $text, $m)) {
            // The command part ends with ": '<text>'" when there is text to show.
            $said = 1 === preg_match("/: '(.*)'$/", $m[3], $s) ? $s[1] : $m[3];
            $said = '' === $m[2] ? $said : str_ireplace($m[2], self::RED . $m[2] . self::C, $said);

            return sprintf('%sSPAM%s detectado desde%s %s%s: %s', self::RED, self::C, self::BLUE, $m[1], self::C, $said);
        }

        return sprintf('%s%s %s%s', self::BLUE, $this->name((string) $message->prefix), self::C, $text);
    }

    /** UID to nickname, SID to server name, anything else unchanged. */
    private function name(string $id): string
    {
        if ($this->isUid($id)) {
            return $this->users->findByUid($id)->nick ?? $id;
        }

        return $this->serverName($id);
    }

    private function serverName(string $id): string
    {
        return $this->servers[$id] ?? $id;
    }

    private function isUid(string $id): bool
    {
        return 1 === preg_match('/^\d[0-9A-Z]{8}$/', $id);
    }

    private function isServer(string $id): bool
    {
        return 1 === preg_match('/^\d[0-9A-Z]{2}$/', $id) || str_contains($id, '.');
    }

    /** Never let a credential reach the channel through the raw dump. */
    private function redact(string $line): string
    {
        $line = (string) preg_replace('/::pass\s+:?\S+/i', '::pass :<oculto>', $line);

        return (string) preg_replace('/[A-Za-z0-9+\/]{40,}={0,2}/', '<oculto>', $line);
    }
}
