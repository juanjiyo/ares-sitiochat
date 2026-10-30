# OperServ (OPeR) parity with the original op.mrc, dbots-services and pulsar

Objective: add to Ares OperServ the OPeR commands that exist in the original network services and are missing in Ares.
Branch: `main` (the user works directly on `main`; the feature branches were merged and deleted) · Delivery: push to `sitiochat` and deploy with `scripts/vps-deploy.sh` · Test runner: `./vendor/bin/phpunit` (full gates on Linux/CI).

## Specs

Sources of truth, in order: `op.mrc` (original OPeR; Linux PC: `/home/juanjo/irc/sitiochat/sistema/op.mrc`, Windows: `C:\IRC\services\sistema\op.mrc`), pulsar `modules/operserv|adminserv/*.c` (for JAIL, UMODE, ELINE, SERVERMAP, CONEXIONES; only on the Windows PC, `C:\IRC\services\pulsar`), then dbots-services `src/OperServ/` (PHP port for UnrealIRCd 6 + UDB, already implements T4–T9 and is verified live; Linux PC: `/opt/dbots-services`).

S1. Channel override commands: `KICK <#canal> <nick> [motivo]`, `OP <#canal> <nick>`, `DEOP <#canal> <nick>`, `MODE <#canal> <modos> [args]`, `LIMPIA <#canal>` (removes `-imnpst`), `INVITE <#canal>` (invites the caller).
S2. Network information: `STATS` (users online), `IRCOPS` (IRC operators online).
S3. Clone handling: `LISTCLONES <nick>`, `KILLCLONES <nick> [motivo]` (G-Line on the host, 1 hour), `BLOCK <nick> [motivo]` (G-Line on the host, 5 hours), `CONEXIONES LIST <min>|VIEW <host>`. Localhost/127.0.0.1 are never G-Lined.
S4. User control: `JAIL <nick>` (parts all channels, `+C`, joins the configured jail channel), `UMODE <nick> +/-modos` (refuses `r` and `h`).
S5. Registration overrides: `APODERA <#canal> [nick]` (gives a registered channel to a nick, default the caller), `DROPNDB <nick>` (removes a registered nick from the database; Root, as in op.mrc).
S6. `SERVERMAP`: tree of linked servers.
S7. `ELINE ADD|DEL|LIST|VIEW|COUNT` (exception lines by IP, persisted, sent as TKL E).
S8. `SPAM LIST|ADD|DEL` (spamfilter rules published to UDB `K::F`).
S9. `SET SUFIJO|NICKSERV|CHANSERV|IPSERV|PASSPROTECT|PREFIJOS <valor>` (UDB `S::` settings; Root, as in op.mrc).
S10. `SETTIME` (network time resync; Root).
S11. Every command: registered in the OperServ registry, listed in HELP, translation keys in the 14 locales (es replies follow op.mrc/pulsar text), a permission in the OperServ permission catalog (STATS/IRCOPS stay IRCop-only), audit records for actions, tests, `ServicesCommandHelpAlignmentTest` count updated.
S12. Gates: phpstan max, php-cs-fixer, deptrac, `./scripts/check-coverage.sh 100 --issues`.

Assumption: not ported because Ares already covers them — `UNGLINE` (= `GLINE DEL`), `VIEWIP` (= NickServ `USERIP`), `RENAME` (= NickServ `RENAME`), `ROOT <password>` (Ares uses configured Root identities, a shared password would weaken it). The user may still ask for them or ask to drop others.

## Tasks

| ID | Spec | Description | Route | Status | Commit |
|---|---|---|---|---|---|
| T1 | S1, S11 | Channel override commands + channel action port | delegated (context backstop) | done | `0d632d9`…`63b01fa` |
| T2 | S2, S11 | STATS, IRCOPS | delegated (context backstop) | done | `f3a5d6f`, `55c7c20` |
| T3 | S3, S11 | LISTCLONES, KILLCLONES, BLOCK, CONEXIONES + users-by-host query | delegated (context backstop) | done | `40e09bf`…`ea65ebf` |
| T4 | S4, S11 | JAIL, UMODE (needs force-part action in the 3 protocols) | delegated (context backstop) | pending | |
| T5 | S5, S11 | APODERA, DROPNDB (cross-context ports to ChanServ/NickServ) | delegated (context backstop) | pending | |
| T6 | S6, S11 | SERVERMAP (needs a server link registry in Irc) | delegated (context backstop) | pending | |
| T7 | S7, S11 | ELINE (migration + TKL E action) — Risk item 1 | delegated (context backstop) | pending | |
| T8 | S8, S11 | SPAM (UDB K::F) — Risk item 3 | delegated (context backstop) | pending | |
| T9 | S9, S10, S11 | SET (UDB S::), SETTIME — Risk item 3 | delegated (context backstop) | pending | |
| T10 | S12 | Final gates on Linux + coverage | inline | pending | |

## Log

L1 (2026-10-29), after the assistant recommended starting with OPeR: "Dale y después te digo si hay que quitar algún comando"
L2 (2026-10-29): "Son de pulsar" — JAIL, UMODE, ELINE, SERVERMAP, CONEXIONES, VIEWIP come from pulsar, not from dbots.
L3: Writers run one at a time (shared files: config/services.yaml, translations/operserv.*.yaml, OperServPermission, HELP_GROUPS, ServicesCommandHelpAlignmentTest), each committing its unit. Anatomy handoff kept outside the repo.
L4: T1 done in `5c0e7ca`: KICK/OP/DEOP/MODE/LIMPIA/INVITE, permissions operserv.kick/op/mode/invite, HELP group `help.group.channels`, shared `ProtectedNetworkTargets` extracted from KILL (KILL tests unchanged and green). Windows: tests/OperServ 929 OK; 2 `LayerDependencyTest` cases fail only on Windows (path separators, also on base) — confirm on Linux in T10. Commands total 81.
L5: T1 was re-sliced into small commits before reaching `main`; `5c0e7ca` never landed. T1 = `0d632d9`…`63b01fa`.
L6: T2 done in `f3a5d6f`, `55c7c20`: STATS and IRCOPS are IRCop-only (no permission), read from a connected users directory port.
L7: T3 done in `40e09bf`…`ea65ebf`. Decisions: clones are grouped by the **real IP** and, when there is none, by host; never G-Line `localhost`, `127.0.0.0/8`, `::1`, or a host/IP where a Root or a role IRCop is connected. KILLCLONES (1 h) and BLOCK (5 h) go through Ares' own `GLINE`, so they show in `GLINE LIST` and are removed with `GLINE DEL`; that leaves **two audit records** (the inner `GLINE ADD` and the command). Default reasons when none is given: `Clones of <nick>` / `Blocked by <bot>` (op.mrc required one; open question for the user).
L8: Live-test fixes, all deployed: `ee7c838` (command log named the new commands UNKNOWN), `0628336` (no "1 conexiones" plural), `3a6b465` then `3dd1c77` (with UnrealUdb a G-Line is a UDB record that only blocks new connections; every G-Line now also KILLs the matching connected users, by host and real IP, and Root/role-IRCop protection also checks the real IP).
L9: `91dc170d`: the command log reads command names from the OPeR registry, so T4–T9 commands are named without editing a list.
L10 (2026-10-29): full CI on Linux green with 100% coverage (`40871933015` on `8d68349`); the 2 `LayerDependencyTest` cases pass on Linux.
