<?php

declare(strict_types=1);

namespace App\OperServ\Application\Port\Out;

final readonly class NetworkUser
{
    /**
     * @param string $ipAddress real IP decoded from $ipBase64; '*' when the IRCd did not send one,
     *                          the raw value when it cannot be decoded, '' when unknown
     */
    public function __construct(
        public string $uid,
        public string $nickname,
        public string $ident,
        public string $hostname,
        public string $ipBase64,
        public bool $identified,
        public bool $ircOperator,
        public string $ipAddress = '',
    ) {}
}
