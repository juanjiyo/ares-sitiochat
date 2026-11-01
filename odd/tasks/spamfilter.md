# SPAMFILTER in OperServ (port of dbots nospam.mrc)

Objective: manage the IRCd's global spamfilters from OPeR (`SPAMFILTER ADD/DEL/LIST`), the IRCd being their only owner; later, a dedicated antispam bot with a private-message trap (T8 design).
Branch: `main`. Delivery: deploy to VPS (`scripts/vps-deploy.sh`), user tests live, then push to `sitiochat` with CI green. Test runner: `./scripts/check-coverage.sh 100 --issues`, PHPStan max, CS-Fixer, deptrac.

## Specs

- S1 (user, 2026-11-01): «Para el bot de SPAM podemos "aprovechar" este módulo de mirc de dbots /home/juanjo/irc/sitiochat/sistema/modulos/modulos-viejos/nospa pasarlo a PHP y mejorarlo.» → «Respecto al antispam, hazlo como quieras».
- S2 (from nospam.mrc): `ADD <palabra> <lugares> <acción> <mensaje>`, `DEL <palabra>`, `LIST`. Places `c p n N P q a t` (+ `ALL`); actions KILL, G-Line, BLOCK. nospam stored them in `spam.db` and re-sent them on every link, mirrored `TKL + F`/`TKL - F` from the network and announced changes in the admins channel.
- S3 (decision, delegated): single owner is the IRCd, as with password-flood G-Lines: `tkldb` already persists global spamfilters across restarts (verified 2026-11-01: tkl.db 56 → 362 bytes). Ares sends `TKL + F` / `TKL - F` and mirrors the global ones in memory (rebuilt on every link); it does not store them.
- S4: wire format (UnrealIRCd 6.2.7 `tkl.c:4764`, `5517-5535`): `:SID TKL + F <targets> <action> <set_by> <expire_at> <set_at> <tkl_duration> <tkl_reason> <simple|regex> :<match>`; `tkl_reason` with spaces encoded as `_`. Targets `c p n N P q d u a t T R` (`n` = private notice, `N` = channel notice; nospam had them swapped). Actions K kill, g gline, Z gzline, z zline, k kline, s shun, S tempshun, b block, d dccblock, v viruschan, w warn (soft variants exist).
- S5: config spamfilters (`spamfilter.conf`) are local and never synced to services (`tkl.c:4733`); SPAMFILTER cannot list nor remove them.
- S6: matches are already announced: `SLOG SPAMFILTER_MATCH` → DebugServ «SPAM detectado» (`NetworkEventTranslator.php:618`). Not part of this work.
- S7 (improvements over nospam): `simple` match with wildcards by default, `-regex` optional; `warn` action available (alert-only mode per T8 design); numbered LIST and `DEL <number|pattern>`; ban duration for ban actions; all 14 locales; HELP group; permission `operserv.spamfilter`.

## Tasks

- T1 — S2–S7 — OPeR SPAMFILTER (inline; no writer trigger: one unit) — implemented: lint:container, PHPStan max, CS-Fixer, deptrac 0, coverage 100% (26422/26422); deployed; live test pending.
- T2 — T8 design — antispam bot with private-message trap — pending, separate feature.

## Log

- L1 (2026-11-01, user): «Para el bot de SPAM podemos "aprovechar" este módulo de mirc de dbots … pasarlo a PHP y mejorarlo.»
- L2 (2026-11-01, user): «Respecto al antispam, hazlo como quieras» → chosen: OperServ command first (≈10 files following the GLINE/IRCd-G-Line pattern) instead of a new bot context (≈25 boilerplate files); the trap needs its own nick, so the bot comes with T2.
- Engram mirror: pending (Engram tools not loaded this session).
