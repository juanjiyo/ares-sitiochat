# GlobalServ: port the staff-announcement service bot from dbots-services

Objective: add `GlobalServ` to Ares as a first-class service bot, porting the commands that exist in
`dbots-services` (`GLOBAL`, `NICK`, `CHAN`, `JOIN`, `PRE-OPER`, `OPER`, `ADMIN`) and
**removing the existing `GLOBAL` command from OperServ**, so the network has one single owner for it.
Branch: `feat/globalserv` (from `main`).
Delivery strategy: single PR, one work unit per command.
Test runner: `php vendor/bin/phpunit` (focused; coverage gate at the end).

## Reference behaviour (dbots-services)

- `NICK <nick> <mensaje>`: admin-only. Rejects a nick already registered or in use. Creates a
  temporary service UID, broadcasts the message to all connected users, then quits the temp UID.
- `CHAN <#canal> <mensaje>`: admin-only. Broadcasts to every member of the channel.
- `JOIN <#canal> <motivo>`: root-only. Forces every connected user to join the channel and broadcasts
  the reason.
- `PRE-OPER <mensaje>`: admin-only. Sends to accounts at `PreOperador` level or above.
- `OPER <mensaje>`: admin-only. Sends to accounts at `Operador` level or above.
- `ADMIN <mensaje>`: root-only. Sends only to exact administrator rank.
- `GLOBAL <mensaje>`: exists in dbots but is intentionally NOT ported, because Ares already exposes
  `GLOBAL` in OperServ.

All broadcasts carry a two-line payload: a `[ Mensaje Global ]`-style header and the message body.

## Specs

- S1 — A `GlobalServ` service bot is registered and reachable by its service nick, following the same
  wiring as the other services (`services.yaml`, IRC listener, help formatter).
- S2 — `NICK <nick> <mensaje>` is admin-only, rejects registered/active nicks, broadcasts through a
  temporary UID and returns the impact count.
- S3 — `CHAN <#canal> <mensaje>` is admin-only and sends to all members of the channel.
- S4 — `JOIN <#canal> <motivo>` is root-only and forces all users into the channel before announcing.
- S5 — `PRE-OPER` and `OPER` broadcast to operators; `ADMIN` is root-only. `CO-ADMIN` is not
  implemented because Ares has no Co-Administrator role.
- S6 — `GLOBAL` is removed from OperServ (command, help entry, permission mapping and its tests) and
  implemented in GlobalServ instead, reusing the existing `GlobalMessageTransport`.
- S7 — Every string exists in all locales and the help catalogue lists the commands.
- S8 — Gates: PHPUnit for `tests/GlobalServ` (and affected suites), `lint:container`, `lint:yaml`,
  PHPStan max 0 errors, Deptrac 0 warnings / 0 errors, CS-Fixer clean.

## Tasks

- [x] T1 — Scaffold `src/GlobalServ` (command interface, registry, listener wiring, help formatter)
      and register the service bot in `config/services.yaml`.
- [x] T2 — Remove `GLOBAL` from OperServ and port it into GlobalServ (reusing `GlobalMessageTransport`).
- [x] T3 — `NICK` use case + command + transport/reply adapters + help + tests.
- [x] T4 — `CHAN` use case + command + tests.
- [~] T5 — `JOIN` use case + command + tests. Needs a new `forceJoin` protocol action
      (`SVSJOIN`) added to `ProtocolServiceActionsInterface` and the three protocol adapters.
- [x] T6 — `PRE-OPER`, `OPER`, `ADMIN` rank broadcasts + tests.
- [ ] T7 — Locale strings and help catalogue entries.

## Log

- L1 — User requirement, verbatim: "Revisa que bots de servicio tiene y sus comandos, luego compara
      con ares-services del vps y haz los comandos que falten en ares que existan en dbots".
- L2 — User decision, verbatim: "Globalerv pero tendrías que eliminar: 22:46 -OPeR(OPeR@bots.sitiochat.es)- › GLOBAL Envía un mensaje global a todos los usuarios conectados."
      Interpreted as: port GlobalServ but drop `GLOBAL`, because it already exists in OperServ.
- L3 — User decision, verbatim: "No es un módulo, es un bot de servicio". GlobalServ is a bounded
      context under `src/GlobalServ`, not a `Module`.
- L4 — 2026-10-27: scaffolding started (`src/GlobalServ` interface, command DTOs, placeholder
      handler). Wiring and the real handler are still pending.
- L5 — User decision, verbatim: "Si haces GlobalServ, tienes que ELIMINAR el coando GLOBAL de OPeR".
      GLOBAL now belongs to GlobalServ; OperServ loses it.
- L6 — 2026-10-27: blocking findings. `JOIN` has no protocol primitive yet (no SVSJOIN/SAJOIN in
      `ProtocolServiceActionsInterface`), so it needs a new action across UnrealUdb, UnrealStandalone
      and InspIRCd. The remaining commands need the full service-bot infrastructure (bot, service,
      registry, notifier, help formatter, listener, DI, translations, tests), which is a multi-session
      change and cannot be completed safely in one turn.
- L7 — 2026-10-28 integration: `GLOBAL` is gone from OperServ and GlobalServ is wired as a service
      bot. Full gates on `integration/verify-all`: PHPUnit 6808/6808, PHPStan max 0 errors, Deptrac
      0 violations, CS-Fixer clean on the touched scopes. The unused `operserv.global.*` translation
      keys were removed and the service-command count dropped to 72. `JOIN` still only broadcasts:
      forcing users into a channel needs a `SVSJOIN`/`SAJOIN` action that Ares does not expose yet.
