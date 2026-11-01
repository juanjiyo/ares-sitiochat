# Password-flood G-Lines visible in OperServ

Objective: the UDB module G-Lines IPs that brute-force passwords; Ares shows those IRCd-owned G-Lines in `GLINE LIST` and removes them with `GLINE DEL`, without owning them.
Branch: `main` (user works directly on main). Delivery: push to `sitiochat` + `scripts/vps-deploy.sh` (standing authorization). Test runner: `./scripts/check-coverage.sh 100 --issues`, PHPStan max, CS-Fixer, deptrac.

## Specs

- S1 (UDB module, done outside this repo): when one IP reaches the `S::flood` attempts (any nick) within its period, the module places `TKL + G * <ip>` signed `UDB:password-flood`, duration `udb::password-flood-gline-time` (seconds; 0/absent = off). Exempt: IRCops, U-lined, localhost, E-Lined IPs. Source: `/home/juanjo/irc/sitiochat/unrealircd-udb/`.
- S2: single owner is the IRCd. Ares never stores these G-Lines in its database nor in UDB.
- S3: Ares parses inbound `TKL + G` / `TKL - G` (burst and live) and keeps an in-memory list of global G-Lines whose setter is exactly `UDB:password-flood`; entries drop at their `expire_at` (the IRCd does not broadcast expiry); the list is rebuilt on every link.
- S4: `GLINE LIST` shows Ares' G-Lines as today, then a separate block with the IRCd password-flood G-Lines (mask, expiry, reason).
- S5: `GLINE DEL <number|mask>` on an entry of that block sends `TKL - G <user> <host> <setter>` to the IRCd and does not touch Ares' repository. `/gline -*@IP` keeps working and Ares drops the entry when it sees the `TKL -`.
- S6: `GLINE CLEAR` does not touch them (user decision for IRCd G-Lines, 2026-10-30).
- User summary (2026-11-01, verbatim): «Quién banea: el servidor IRC … Qué hace Ares: solo mira y avisa … Cómo quitarlo: `/msg OPeR GLINE DEL <número o IP>` … o `/gline -*@IP` … Cuando caduca: el ban se va solo pasada la hora, y Ares lo quita de su lista.» → «Correcto. Continua»

## Tasks

- T1 — S1 — module: setter `UDB:password-flood`, `TKL +` via `do_cmd` + `find_tkline_match` — inline — done, deployed on the IRCd 2026-11-01 10:21 (rehash; client probe gets 001).
- T2 — S2–S6 — Ares: inbound TKL registry, `GLINE LIST` block, `GLINE DEL` path, tests, 14 languages — inline — done: lint:container, lint:yaml, PHPStan max (src+tests), CS-Fixer, Deptrac (0 errors), `check-coverage.sh 100` = 100% (26109/26109). Commit: see git log `feat(operserv): show and remove IRCd password-flood G-Lines`.
- T3 — S1–S6 — deploy module + Ares, live test — pending.

## Log

- L1 (2026-11-01, user): «¿Se puede hacer que sea visible en Ares para hacer: /msg OPeR GLINE DEL <IDQUESEA>?»
- L2 (2026-11-01, user): «Pero entonces … habrían dos propietarios de un mismo GLINED. ¿Cómo podríamos gestionar eso para que solo haya un propietario pero que OPeR puede eliminar ese GLINED independiente de sei hago /gline -@ip?» → design: IRCd owns, Ares is a view.
- L3: evidence — Ares ignores inbound TKL (`src/DebugServ/Adapter/In/Irc/NetworkEventTranslator.php:65`); Unreal sends all global TKLs on link (`tkl_sync`) and does not broadcast expiry (`tkl_expire_entry`); UDB only treats `UDB:managed` lines as its own (`udb.c:25096`).
- Engram mirror: pending (Engram tools unavailable this session).
