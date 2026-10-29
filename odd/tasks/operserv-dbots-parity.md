# OperServ (OPeR) parity with the original op.mrc, dbots-services and pulsar

Objective: add to Ares OperServ the OPeR commands that exist in the original network services and are missing in Ares.
Branch: `feature/operserv-dbots-parity` · Delivery: `ask-on-risk` · Test runner: `./vendor/bin/phpunit` (Windows: focused tests only, `--no-coverage`; full gates on Linux/CI).

## Specs

Sources of truth, in order: `C:\IRC\services\sistema\op.mrc` (original OPeR), `C:\IRC\services\pulsar\modules\operserv|adminserv\*.c` (for JAIL, UMODE, ELINE, SERVERMAP, CONEXIONES), then dbots-services `src/OperServ/Adapter/In/Irc/OperServCommandHandler.php` (PHP port, replies in English).

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
| T1 | S1, S11 | Channel override commands + channel action port | delegated (context backstop) | done | `5c0e7ca` |
| T2 | S2, S11 | STATS, IRCOPS | delegated (context backstop) | pending | |
| T3 | S3, S11 | LISTCLONES, KILLCLONES, BLOCK, CONEXIONES + users-by-host query | delegated (context backstop) | pending | |
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
