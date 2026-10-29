<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\Out\Irc;

use App\Irc\Application\Port\In\SenderView;
use App\OperServ\Application\Port\Out\NetworkUser;

use function base64_decode;
use function inet_ntop;

/** Maps the public Irc user view to the OperServ consumer-owned NetworkUser. */
final class NetworkUserMapper
{
    private function __construct() {}

    public static function fromSenderView(SenderView $user): NetworkUser
    {
        return new NetworkUser(
            $user->uid,
            $user->nick,
            $user->ident,
            $user->hostname,
            $user->ipBase64,
            $user->isIdentified,
            $user->isOper,
            self::ipAddress($user->ipBase64),
        );
    }

    /**
     * Same decoding as the Irc NetworkUser::getIpAddress(), which OperServ adapters may not import:
     * '*' stays '*', and a value that is not base64 of a binary address is returned unchanged.
     */
    private static function ipAddress(string $ipBase64): string
    {
        if ('*' === $ipBase64) {
            return '*';
        }

        $binary = base64_decode($ipBase64, strict: true);
        if (false === $binary) {
            return $ipBase64;
        }

        $ip = inet_ntop($binary);

        return false !== $ip ? $ip : $ipBase64;
    }
}
