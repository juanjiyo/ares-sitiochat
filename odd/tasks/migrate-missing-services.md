# Reimplementar en Ares los servicios que existen en dbots-services

Objective: añadir a ares-sitiochat los servicios que dbots-services tiene y Ares aún no implementa, preservando los nicks cortos (HeLP, CeNTeR, CLoNeS, GlobalServ) y el comportamiento documentado en `/opt/dbots-services/docs/commands.md`.
Branch: `feature/migrate-missing-services`
Delivery strategy: `ask-on-risk`
Test runner: `./vendor/bin/phpunit`

## Specs

S1. Reimplementar `HeLPServ` como servicio `HeLP` con los temas de ayuda documentados en dbots: `AYUDA`/`HELP`, `UMODES`, `CMODES`, `BOTS`, `NICK`, `CANAL`, `CREDITOS` y respuesta para temas desconocidos.
S2. Reimplementar `GlobalServ` con los comandos de anuncios globales: `GLOBAL`, `NICK`, `CHAN`, `JOIN`, `PRE-OPER`, `OPER`, `CO-ADMIN`, `ADMIN`, respetando los permisos de Administrador/Root documentados.
S3. Reimplementar `CeNTeRServ` como `CeNTeR`: gestión del staff ladder (`PREOPER`, `OPER`, `ADMIN`, `REMOPER`, `ROOT`) y comandos `RAW`/`NORAW`. Los comandos `KILL`/`GLINE`/`CLONES` deben seguir viviendo en `OPeR`.
S4. Reimplementar `ClonesServ` como `CLoNeS`: excepciones de clones (`PERM`, `ADD`, `RENEW`, `EDIT`, `DEL`, `INFO`, `LIST`).
S5. Reimplementar `NoServ` con suscripción de noticias (`ALTA`/`BAJA`), `EMAIL`, `MOTD`, `NICK`, `REPRESENTANTES`, `NOTICIAS` y `SERVIDOR`.
S6. Reimplementar el relay `DeBuG` que escribe eventos de red en el canal configurado.
S7. Reimplementar el módulo `Sumidero` como módulo cargable desde `CeNTeR MODULO`.
S8. Reimplementar el módulo `Ping`.
S9. Todos los nuevos servicios deben integrarse con el registro de comandos, el bot pseudo-cliente, las traducciones en los 14 idiomas, HELP y los tests del proyecto siguiendo `.agents/services.md`.
S10. Los cambios deben pasar `phpstan analyse src/ tests/ --level=max`, `./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php` y `./scripts/check-coverage.sh 100 --issues`.

## Tasks

| ID | Spec | Description | Route | Status | Commit |
|---|---|---|---|---|---|
| T1 | S1 | HeLPServ: Domain + Application (help topics, no persistence) | inline | done | 0000000 |
| T2 | S1 | HeLPServ: Adapter In IRC (commands, HELP, bot) | inline | done | 0000000 |
| T3 | S1 | HeLPServ: Translations + wiring + tests | inline | done | 0000000 |
| T4 | S2 | GlobalServ: Domain + Application (broadcast intents) | inline | pending | |
| T5 | S2 | GlobalServ: Adapter In IRC + network output port | inline | pending | |
| T6 | S2 | GlobalServ: Translations + wiring + tests | inline | pending | |
| T7 | S3 | CeNTeRServ: staff ladder Domain/Application | delegated | pending | |
| T8 | S3 | CeNTeRServ: RAW/NORAW and permission catalog | delegated | pending | |
| T9 | S3 | CeNTeRServ: IRC adapter + tests + translations | delegated | pending | |
| T10 | S4 | ClonesServ: clone exception Domain/Application + allow include projection | delegated | pending | |
| T11 | S4 | ClonesServ: IRC adapter + tests + translations | delegated | pending | |
| T12 | S5 | NoServ: news subscription and broadcast Domain/Application | delegated | pending | |
| T13 | S5 | NoServ: IRC adapter + tests + translations | delegated | pending | |
| T14 | S6 | DeBuG relay: event subscriber + channel writer | inline | pending | |
| T15 | S7 | Sumidero module: anti-spam sink + verification | delegated | pending | |
| T16 | S8 | Ping module: simple ping/pong pseudo-client | inline | pending | |
| T17 | S1-S10 | Final quality gates and coverage | inline | pending | |

## Log

L1: [Usuario] "5. MIGRAR TODOS (los que no existan en Ares)"
L2: Auditoría previa: dbots-services tiene 13 servicios/módulos; ares ya tiene NiCK, CHaN, MeMo, OPeR y CReG. Faltan HeLPServ, CeNTeRServ, ClonesServ, GlobalServ, NoServ, DeBuG, Sumidero y Ping.
L3: Se corrigió el certificado TLS de UnrealIRCd (CN `irc.sitiochat.es` + SAN) para resolver desconexiones de clientes que verifican hostname.
