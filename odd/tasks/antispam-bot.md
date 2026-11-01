# AntiSpam bot

Objective: a dedicated service bot "AntiSpam" that joins the channels it is told to, watches what is written there, G-Lines the IP of whoever posts spam and suspends the nick of a repeat offender.
Branch: `main`. Delivery: deploy to VPS (`scripts/vps-deploy.sh`), user tests live, then push to `sitiochat` with CI green. Test runner: `./scripts/check-coverage.sh 100 --issues`, PHPStan max, CS-Fixer, deptrac.

## Specs

- S1 (user, verbatim): «Para el bot de SPAM podemos "aprovechar" este módulo de mirc de dbots /home/juanjo/irc/sitiochat/sistema/modulos/modulos-viejos/nospa pasarlo a PHP y mejorarlo.»
- S2 (user, verbatim): «ALTO! quería un bot llamado AntiSpam para meterlo en canales y que vigile»
- S3 (user, verbatim): «Yo también me expliqué mal. Me refería q que hicieras el bot como quisieras. 4 y si reincide, suspend al nick» — "4" = option «G-Line a la IP» offered for what the bot does on spam in a channel.
- S4 (design, delegated by S3): the bot's nick is `AntiSpam`; it is introduced on every link like the other service bots.
- S5 (design): `/msg AntiSpam JOIN #canal` / `PART #canal` / `CHANNELS` choose the watched channels; the list is stored by services and the bot rejoins them on every link.
- S6 (design, from nospam ADD/DEL/LIST): `/msg AntiSpam ADD <patrón> <motivo>`, `DEL <número|patrón>`, `LIST [filtro]` manage the spam patterns (wildcards `*` `?`, case-insensitive), stored by services.
- S7 (design): on a channel message matching a pattern from a non-exempt user, the bot G-Lines `*@<ip>` through OperServ's GLINE (so it shows in `GLINE LIST` and `GLINE DEL` removes it; OperServ's protections for Root/IRCops apply), duration 1d, reason = the pattern's reason, set by `AntiSpam`.
- S8 (design, "si reincide, suspend al nick"): if the offender is identified to a registered nick that already has an AntiSpam offense in the last 30 days, the bot also suspends that nick through NickServ (duration 30d, reason «AntiSpam: <motivo>»). Unregistered offenders only get the G-Line.
- S9 (design): exempt: IRCops, service bots, the channel's ops (+o and above).
- S10 (design): every action is announced in the opers/debug channel and audited.
- S11 (user, verbatim, answering «¿Quién puede meter a AntiSpam en un canal? 1. Solo los IRCops. 2. También el fundador»): «Sí, solo IRCops» → all AntiSpam commands are IRCop-only, gated by permission `antispam.manage`.

## Tasks

- T1 — S4–S5, S11 — bot skeleton: context, introduction, help, JOIN/PART/CHANNELS with persistence and rejoin — inline — pending.
- T2 — S6 — patterns ADD/DEL/LIST with persistence — inline — pending.
- T3 — S7, S9–S10 — channel watch → G-Line via a new OperServ Port\In — inline — pending.
- T4 — S8 — offense memory + suspend via a new NickServ Port\In — inline — pending.

## Log

- L1 (2026-11-01, user): S1.
- L2 (2026-11-01, user): «Respecto al antispam, hazlo como quieras» → misread as "put it wherever": built OperServ SPAMFILTER (`867bcd77`).
- L3 (2026-11-01, user): S2 → work stopped; L4: S3. SPAMFILTER reverted in `8627268e` (never pushed), redeployed.
- L5: evidence — channel PRIVMSG reaches services as `MessageReceivedEvent` when a services pseudo-client is in the channel; `ServiceCommandGateway` only routes messages addressed to a bot (`src/Irc/Adapter/In/Event/ServiceCommandGateway.php:71`). Protocol actions exist: `joinChannelAsService`, `partChannelAsService`, `kickFromChannel` (`ProtocolServiceActionsInterface.php:57,120,129`). No OperServ/NickServ Port\In exists today for G-Line or suspend; deptrac lets a context's Adapter use other contexts' `Application\Port\In`.
- L6 (2026-11-01, user): «Haz todo lo pendiente. Y a tu pregunta: Sí, solo IRCops» → S11 rewritten; pending Ares commits pushed to sitiochat (`70637515`).
- L7 (2026-11-01, user): «Terminalo si quieres o lo aparcas. Como tu quieras» → parked on local branch `wip/antispam-bot` to make room for upcoming OperServ work. State: GlobalServ plumbing copied to `src/AntiSpam` + `tests/AntiSpam` with renames (bot, service, context, registry, listener, help, security, nick-account port); still to adapt: permission set (single `antispam.manage`), bot realname, tests that reference GlobalServ commands, services.yaml wiring, translations; T1–T4 not started beyond that.
- Engram mirror: pending (Engram tools not loaded this session).
