# Staff ranks in OperServ (CeNTeR model) replacing IRCOP/ROLE

Objective: manage network staff the way dbots CeNTeR did — one command per rank (`PREOPER|OPER|ADMIN|ROOT ADD|DEL|LIST <nick>`) that assigns everything automatically — and remove the generic `IRCOP` and `ROLE` commands.
Branch: `main`. Delivery: deploy to VPS (`scripts/vps-deploy.sh`), user tests live, then push to `sitiochat` with CI green. Test runner: `./scripts/check-coverage.sh 100 --issues`, PHPStan max, CS-Fixer, deptrac.

## Specs

- S1 (user, verbatim): «En OPeR quiero reemplazar y quitar: 1. Reemplazar TODO lo de IRCOP ADD/DEL 2. Quitar/eliminar: TODO de ROLE ADD .. 1a. Reemplazar por como gestiona CeNTeR (mirc dbots) para añadir nuevos staff. Osea: los comandos serían: /msg OPeR OPER ADD Pedro (y se le asignan los roles de forma automática) /msg OPeR OPER DEL Pedro (Y se elimina de la BD junto a los permisos) y así sucesivamente»
- S2 (user, verbatim): «COADMIN fuera» → ranks: PREOPER, OPER, ADMIN, ROOT.
- S3 (from `sitiochat/sistema/ce.mrc`): each `<RANK> ADD <nick>` requires a registered nick that is not already staff, records the rank, sets the IRCd oper level and the vhost `<nick>.<rank>.<red>`, announces it in the opers channel; `DEL` removes all of it; `LIST` lists the rank. CeNTeR access: PREOPER by Co-Admin+, OPER by Admin+, ADMIN/ROOT by Root (ROOT ADD only by the main Root).
- S4 (user approved the proposal with «Si»): cumulative ladder, each rank has its own plus everything below:
  - PREOPER: consult and help in channels — INFO/LIST/HISTORY of nicks and channels, WHOIP/USERIP, OperServ KICK/OP/MODE/INVITE, CRegServ pending/denied lists, GlobalServ to Pre-Opers.
  - OPER: + sanctions — KILL, GLINE, KILLCLONES/BLOCK, JAIL, SUSPEND (nicks and channels), SASET, RENAME, CRegServ approve/deny, GlobalServ to Opers and channels.
  - ADMIN: + network management — ELINE, GEOIP, MOTD, UMODE, DROP, FORBID, NOEXPIRE, RESTORE, SAREGISTER, VREG, BLACKMAIL, network-wide GLOBAL, manage PREOPER and OPER.
  - ROOT (netadmin): everything — RAW, forced DROP, SET PASSPROTECT, manage ADMIN.
- S6 (user, verbatim): «PREOPER solo ROOT y ADMIN» → PREOPER ADD/DEL only by ADMIN and ROOT (as proposed; OPER also by ADMIN/ROOT; ADMIN by ROOT; ROOT by the main Root).
- S5 (user, verbatim): «Quiero decir que Root en dbots (mirc) equivale a netadmin» → rank name stays ROOT; it carries the UnrealIRCd operclass `netadmin`. Proposed operclasses for the rest: PREOPER `locop`, OPER `globop`, ADMIN `admin`.

## Tasks

Design: keep the role/assignment model (`oper_roles`, `oper_ircops`, permissions) and the existing `ManageIrcopHandler` (registration check, assignment, network apply of operclass/modes/vhost, audit); the four ranks become code-defined roles named PREOPER/OPER/ADMIN/ROOT, synced into the role store on every link (the existing `ROOT` role and its 2 IRCops become the ROOT rank). Management permissions: `operserv.staff` (manage PREOPER/OPER, granted to ADMIN), `operserv.staff.admin` (manage ADMIN, granted to ROOT); managing ROOT requires the configured main Root; SET requires new `operserv.set` (granted to ROOT). Vhost pattern `<rank>.sitiochat.es` → `<nick>.<rank>.sitiochat.es`.

- T1 — S2, S4, S5 — `StaffRank` (operclass, vhost, cumulative permissions; ROOT = whole catalog), role sync on link, new permissions, SET → `operserv.set` — inline — done `19379f97`.
- T2 — S1, S3, S6 — `PREOPER|OPER|ADMIN|ROOT ADD|DEL|LIST <nick>` on top of `ManageIrcopHandler`; reject a nick that is already staff; help group; 14 locales — inline — done `4a17f9d6`.
- T3 — S1 — remove `IRCOP` and `ROLE` commands (and the `ManageRole` use case, its texts and tests) — inline — done (see git log `refactor(operserv): remove IRCOP and ROLE`); also removed the ROLE-only ports/adapters, kept and trimmed `OperatorRoleNetworkProjection` (refreshVhost/refreshOperclass) so the rank sync applies changed vhost/operclass to existing holders.
- T4 — S1–S6 — deploy, live test, push — done (tested live, pushed).

## Log

- L1 (2026-11-01, user): S1. L2: S2.
- L3: evidence — VPS DB today: one role `ROOT` (operclass `netadmin`, no vhost/modes) with 2 IRCops (`oper_roles`, `oper_ircops`, `oper_role_permissions`). GlobalServ already maps ranks to operclasses: Pre-Operador = any, Operador = globop+, Administrador = admin (`OperServOperatorRankLookup.php`). Permission catalog: ~70 IRCop permissions across OperServ, NickServ, ChanServ, CRegServ, GlobalServ (+ MemoServ provider).
- L4 (2026-11-04, user): ranks done and tested live; task closed.
- Engram mirror: pending.
