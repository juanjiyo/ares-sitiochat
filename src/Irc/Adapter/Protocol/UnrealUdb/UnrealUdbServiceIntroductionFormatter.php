<?php

declare(strict_types=1);

namespace App\Irc\Adapter\Protocol\UnrealUdb;

use function sprintf;

/**
 * UnrealUdb: introduce a service pseudo-client with a UID line.
 * Format: :serverSid UID nickname hopcount timestamp username hostname uid servicestamp usermodes virtualhost cloakedhost ip :gecos.
 */
final readonly class UnrealUdbServiceIntroductionFormatter
{
    private const array SERVICE_UMODES = [
        'nickserv' => '+BHdIopqrSz',
        'chanserv' => '+BHdIopqrSz',
        'memoserv' => '+BHdIopqrRSz',
        'cregserv' => '+BHdIopqrSz',
        'operserv' => '+BHdIopqrRSz',
    ];

    /** @param string $botSwhois extra WHOIS line for every service bot ('' for none) */
    public function __construct(
        private string $botSwhois = '',
    ) {}

    public function formatIntroduction(
        string $serverSid,
        string $nick,
        string $ident,
        string $host,
        string $uid,
        string $realname,
        string $serviceName,
    ): string {
        $ts = time();
        $umodes = self::SERVICE_UMODES[$serviceName] ?? '+BHSdioqrz';

        return sprintf(
            ':%s UID %s 1 %d %s %s %s 0 %s * * * :%s',
            $serverSid,
            $nick,
            $ts,
            $ident,
            $host,
            $uid,
            $umodes,
            $realname,
        );
    }

    /** The SWHOIS line that follows the introduction, or null when none is configured. */
    public function formatSwhois(string $serverSid, string $uid): ?string
    {
        if ('' === $this->botSwhois) {
            return null;
        }

        return sprintf(':%s SWHOIS %s + services 0 :%s', $serverSid, $uid, $this->botSwhois);
    }
}
