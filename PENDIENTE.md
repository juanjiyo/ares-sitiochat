# Pendiente — Ares (ares-sitiochat)

Estado a 2026-10-29.

## Índice

- Si eres una IA que continúa este trabajo
- 1. OPeR — paridad con `op.mrc`, pulsar y dbots (hecho, revisión pendiente, T4–T10, decisiones)
- 2. Servicios de dbots que Ares no tiene (CeNTeR, CLoNeS, NoServ, DeBuG, módulos)
- 3. Huecos en servicios que ya existen (CHaN, NiCK, CReG, MeMo)
- 4. Infraestructura y mantenimiento (VPS, CI, seguridad, entorno)
- Referencias
- Anexo A — Entorno, trampas conocidas y preferencias del usuario
- Anexo B — OPeR: especificación de los comandos a portar
- Anexo C — Anatomía de un comando de OPeR en Ares

## Si eres una IA que continúa este trabajo

> **Desde el 2026-10-29 el trabajo continúa en el PC Linux Mint del usuario**, no en Windows. Este documento vive en el repositorio (`PENDIENTE.md` en la raíz de `ares-sitiochat`), así que basta con `git pull`. Ver «Cómo continuar desde el PC Linux» justo debajo.

### Cómo continuar desde el PC Linux

1. **Obtener el código** (recomendado: clon limpio, porque `/opt/ares` mezcla otro remoto y tiene trabajo sin commitear de sesiones anteriores):
   ```bash
   git clone https://github.com/juanjiyo/ares-sitiochat.git ~/ares-sitiochat   # o: cd /opt/ares && git fetch sitiochat && git status
   cd ~/ares-sitiochat && composer install
   ```
   Si se usa `/opt/ares`: sus remotos son `origin` = ares-irc-services y `sitiochat` = **ares-sitiochat** (el bueno). Tiene cambios sin commitear (traducciones de CReG y otros) y dos `stash` (`pre-existing WIP round 2`, `geoip wip`): **no borrarlos**; preguntar al usuario antes de tocarlos.
2. **Comprobar el entorno**: `php -v` (8.5), `composer -V`, `gh auth status` (para el CI), `ssh -i ~/.ssh/irc_vps_key irc@5.250.189.222 true` (la clave del VPS se creó en este PC). Conviene un alias en `~/.ssh/config`:
   ```
   Host vps-irc
       HostName 5.250.189.222
       User irc
       IdentityFile ~/.ssh/irc_vps_key
       IdentitiesOnly yes
   ```
3. **En Linux sí corre todo**: la suite completa (`./scripts/check-coverage.sh 100 --issues`, cobertura al 100%), PHPStan, deptrac y CS-Fixer. No hay los problemas de Windows del Anexo A (CRLF, `pcntl`, `LayerDependencyTest`).
4. **Desplegar**: `scripts/vps-deploy.sh <commit>` (empaqueta el commit con `git archive`, lo sube y hace en el VPS copia de seguridad, cambio de carpeta, `cache:clear`, estado de migraciones, arranque y comprobación de bots y errores). Requiere el alias `vps-irc`.
5. **Siguiente tarea**: §2.4 (DeBuG). Antes, cerrar las pruebas en vivo pendientes de §1.1.

Lee en este orden antes de tocar nada:

1. Este documento entero. Los anexos del final tienen todo el detalle técnico:
   - **Anexo A** — Entorno, trampas conocidas y preferencias del usuario (castellano neutro sin voseo, commits sin atribución a IA, máquinas, ramas, Windows, CI, Gentle AI).
   - **Anexo B** — Especificación de cada comando de OPeR y sus fuentes (`op.mrc`, pulsar, dbots).
   - **Anexo C** — Anatomía de un comando de OPeR en Ares: contrato, registro, permisos, traducciones, HELP, tests y acciones de red (en inglés técnico).
2. `ares-sitiochat/AGENTS.md` y los `.agents/*.md` que te toquen (`services.md`, `testing.md`, `architecture.md`).
3. `odd/tasks/operserv-dbots-parity.md` (en `main`).

Punto de partida: cerrar las pruebas en vivo pendientes de §1.1 y empezar §2.4 (DeBuG), que es lo último que pidió el usuario. Después siguen T4–T9 de OPeR. La revisión con Gentle AI de los bloques A–E quedó aplazada por decisión del usuario (solo existía en Windows).

Estado de las ramas (actualizado 2026-10-29 12:02): T1–T3 ya están **en `main` y subidos** (`b9076d3..ea65ebf`, 17 commits). Las ramas `feature/operserv-dbots-parity` y `-sliced` se borraron; el usuario prefiere trabajar directamente en `main` para no acumular ramas. Tabla histórica:

| Rama | Commit | Contenido |
|---|---|---|
| `main` | `ea65ebf` | Igual que GitHub, con T1–T3 |
| `feature/operserv-dbots-parity` | `16a2ca5` | T1–T3 en 4 commits grandes (original, se puede borrar cuando la `-sliced` esté integrada) |
| `feature/operserv-dbots-parity-sliced` | `ea65ebf` | **T1–T3 en 17 commits pequeños**, mismo árbol final que `16a2ca5`. Es la que hay que revisar e integrar |

Commits de la rama `-sliced`, en orden y agrupados en los bloques de revisión:

| Bloque | Commit | Mensaje |
|---|---|---|
| A | `0d632d9` | refactor(operserv): extract ProtectedNetworkTargets from the KILL handler |
| A | `aa89783` | feat(operserv): add channel lookup and channel override action ports |
| A | `fee6262` | feat(operserv): add the KICK channel override use case |
| A | `a6d0417` | feat(operserv): add channel override command KICK |
| B | `3370f35` | feat(operserv): add the OP and DEOP channel override use case |
| B | `27f4c21` | feat(operserv): add channel override commands OP and DEOP |
| B | `e04e760` | feat(operserv): add the MODE and LIMPIA channel override use case |
| B | `c37156e` | feat(operserv): add channel override commands MODE and LIMPIA |
| C | `63b01fa` | feat(operserv): add channel override command INVITE |
| C | `22bff34` | docs(operserv): track the OPeR parity feature and record T1 |
| C | `f3a5d6f` | feat(operserv): add the connected users directory and network info use cases |
| C | `55c7c20` | feat(operserv): add STATS and IRCOPS |
| D | `40e09bf` | feat(operserv): group connected sessions by host |
| D | `296aa54` | feat(operserv): add the clone host G-Line use case |
| D | `09df205` | feat(operserv): add clone command LISTCLONES |
| E | `f31a953` | feat(operserv): add clone commands KILLCLONES and BLOCK |
| E | `ea65ebf` | feat(operserv): add clone command CONEXIONES |

Los 17 commits pasan `php -l`, `lint:container` y `phpunit tests/OperServ tests/Shared` en Windows. PHPStan max está limpio en el último. **Falta** la suite completa con cobertura en Linux.

---

| Dónde | Qué hay |
|---|---|
| Repositorio local | `C:\IRC\services\ares-sitiochat` → `github.com/juanjiyo/ares-sitiochat` |
| `main` (GitHub) | `3dd1c77` y posteriores (este documento) — T1–T3 de OPeR, arreglos de la prueba en vivo y de `symfony/mime` (CVE-2026-45070). CI en verde (run `40876795967`) |
| Producción (VPS `5.250.189.222`) | `/home/irc/ares-sitiochat`, commit `3dd1c77` (desde 2026-10-29 14:19), SQLite `var/data/ares.db`, arranque manual con `nohup` |
| Trabajo en curso | Rama `feature/operserv-dbots-parity-sliced` (17 commits pequeños, sin subir) |

Convenciones que aplican a **todo** lo que queda (de `AGENTS.md`):

- Un comando nuevo está terminado solo si: está en el registro del servicio, sale en `HELP`, tiene las claves de traducción en los **14 idiomas** (ca de el en es eu fr gl it nl pl pt ro tr), tiene test de enrutado/ayuda y, si es de IRCop, permiso en el catálogo y test de autorización por rol y Root.
- Cada comando nuevo sube en 1 el total de `tests/Shared/ServicesCommandHelpAlignmentTest.php` (ahora **87** en la rama de OPeR).
- Arquitectura hexagonal por contexto: el comando (Adapter/In) no contiene lógica; la lógica va en un caso de uso (Application) y las acciones de red en un puerto propio del servicio con su adaptador `ActiveConnection*`.
- Controles finales (solo en Linux; en Windows falta `pcntl`): `php bin/console lint:container`, `lint:yaml`, PHPStan `--level=max`, PHP-CS-Fixer, deptrac y `./scripts/check-coverage.sh 100 --issues` (cobertura de líneas al **100%**).
- Fuente de verdad del comportamiento y de los textos en español: los `.mrc` originales de `C:\IRC\services\sistema`; para lo que viene de pulsar, `C:\IRC\services\pulsar\modules`.

---

## 1. OPeR — paridad con `op.mrc`, pulsar y dbots

Documento de seguimiento en el repo: `odd/tasks/operserv-dbots-parity.md`.

### 1.1. Hecho, pendiente de revisión y entrega

| Tarea | Comandos | Permiso | Estado |
|---|---|---|---|
| T1 | `KICK <#canal> <nick> [motivo]`, `OP`/`DEOP <#canal> <nick>`, `MODE <#canal> <modos> [args]`, `LIMPIA <#canal>` (`-imnpst`), `INVITE <#canal>` | `operserv.kick`, `operserv.op`, `operserv.mode`, `operserv.invite` | Hecho |
| T2 | `STATS` (usuarios conectados), `IRCOPS` (IRCops conectados) | Solo IRCop | Hecho |
| T3 | `LISTCLONES <nick>`, `KILLCLONES <nick> [motivo]` (G-Line 1 h), `BLOCK <nick> [motivo]` (G-Line 5 h), `CONEXIONES LIST <min>` / `VIEW <host>` | `operserv.clones`, `operserv.conexiones` | Hecho |

Detalles ya resueltos:
- `KICK` y `DEOP` no actúan sobre Root ni sobre IRCops identificados con rol (misma protección que `KILL`, extraída a `ProtectedNetworkTargets`).
- Los clones se agrupan por **IP real** y, si no hay, por host. Nunca se banea `localhost`, `127.0.0.0/8` ni `::1`, ni un host donde haya un Root o IRCop protegido.
- Las G-Lines de `KILLCLONES`/`BLOCK` pasan por el `GLINE` de Ares: salen en `GLINE LIST` y se quitan con `GLINE DEL`.

Pasos que faltan, en orden:

- [ ] **Revisión por bloques** con Gentle AI (el cambio entero, ~7600 líneas, no cabe en una revisión). Bloques previstos sobre la rama `-sliced`:
  - A: commits 1–4 (`ProtectedNetworkTargets`, puertos de canal, `KICK`) — ~1530 líneas. *Preparado, falta tu consentimiento* (ver «Revisión del bloque A» en §1.1).
  - B: commits 5–8 (`OP`/`DEOP`, `MODE`/`LIMPIA`) — ~1770 líneas.
  - C: commits 9–12 (`INVITE`, documento, `STATS`/`IRCOPS`) — ~1430 líneas.
  - D: commits 13–15 (agrupación por host, G-Line de clones, `LISTCLONES`) — ~1350 líneas.
  - E: commits 16–17 (`KILLCLONES`/`BLOCK`, `CONEXIONES`) — ~1540 líneas.
#### Revisión del bloque A — pendiente de tu decisión

Preparada el 2026-10-29 sobre el commit `a6d0417` (base `b9076d3`), lentes **riesgo** y **fiabilidad**.

| Campo | Valor |
|---|---|
| Titular | Gentle AI puede revisar este cambio antes de darlo por terminado. |
| Motivo | Puede ayudar a detectar posibles problemas de seguridad. |
| Beneficio | Revisar tarda un poco más y deja el resultado más seguro. |
| Riesgo | Alto — 47 archivos, 1529 líneas |
| Evidencia | Seguridad en `src/OperServ/Application/Security/OperServIrcopPermission.php` |

Opciones:

1. **Revisar este cambio** (`granted`): revisa solo este cambio; los próximos cambios de riesgo medio o alto volverán a preguntar, y la entrega necesita una aprobación aparte.
2. **Saltar esta vez** (`declined`): salta solo este cambio; no se crea ningún registro de revisión y las revisiones futuras siguen activadas.

Para desactivar las revisiones para siempre: `gentle-ai review mode disable`.

Cómo retomarlo: con el repo en `a6d0417` (`git checkout --detach a6d0417`), volver a pedir el estado con
`gentle-ai review status --cwd C:\IRC\services\ares-sitiochat --contract gentle-ai.review-integration/v2 --agent claude-code --next-transition --base-ref b9076d3 --committed-only --lenses risk,reliability --lenses-reason "<qué se tocó>"`
y ejecutar el `start` que devuelva (la preparación anterior no se reutiliza si el estado cambió). Después de A vendrán B–E, cada uno con su propia pregunta de consentimiento.

- [ ] Actualizar `odd/tasks/operserv-dbots-parity.md`: ahora solo marca T1 como hecho, y con el commit antiguo `5c0e7ca`. Hay que poner T1 → `0d632d9`…`63b01fa`, T2 → `f3a5d6f`, `55c7c20` y T3 → `40e09bf`…`ea65ebf`, y añadir al Log las decisiones de T2/T3 (agrupación por IP real, doble auditoría, motivos por defecto).
- [ ] Corregir lo que encuentre la revisión (una corrección por bloque, luego una comprobación).
- [x] **CHECK completo en Linux** — hecho el 2026-10-29: CI run `40871933015` sobre `8d68349` en verde (lint, CS, PHPStan max, deptrac, suite completa con cobertura al 100% y las 4 migraciones). El primer intento (`40870007143`) se quedó en 99,98% por 4 constructores privados sin test; se corrigió en `8d68349`. Los 2 tests de `LayerDependencyTest` pasan en Linux.
- [x] Juntado en `main` (`b9076d3..8d68349`) y CI en verde.
- [x] **Desplegado en el VPS** el 2026-10-29 a las 13:31 (hora del VPS): commit `8d68349`. Parada limpia en 2 s, copia en `~/backups/ares-data-20261029-133118.tar.gz`, versión anterior en `/home/irc/ares-sitiochat.old-20261029-133118`, 0 migraciones nuevas, enlace `unrealudb` establecido, los 7 bots presentados (NiCK, CHaN, MeMo, CReG, GlobalServ, HeLP, OPeR), 0 errores en `var/log` e integridad SQLite `ok`.
- [ ] **Prueba en vivo** contra el UnrealIRCd real (sin que OPeR esté dentro del canal):
  - [x] `KICK` — probado el 2026-10-29 13:41 en `#SitioChat` con `CAP3781`: sintaxis sin argumentos, canal sin `#` y expulsión con motivo funcionan; la red muestra «expulsado por OPeR [Test]».
  - [ ] `OP` / `DEOP`
  - [x] `MODE` — probado el 2026-10-29 13:44–13:45 en `#Zamora`: `+mk 123` y `-mk 123` aplicados con su parámetro; el canal muestra «OPeR pone modo [+mk 123]» / «[-mk 123]».
  - [x] `LIMPIA` — probado el 2026-10-29 13:42 en `#Zamora`: sintaxis y respuesta correctas; el log del VPS confirma `:003E00007 MODE #Zamora -imnpst`.
  - [ ] `INVITE`
  - [x] `CONEXIONES LIST` — probado el 2026-10-29 13:46: sin argumentos, `LIST` sin límite, `LIST 1` (rechazado: debe ser mayor que 1) y `LIST 2` («No hay IPs con al menos 2 conexiones simultáneas») responden bien.
  - [x] `CONEXIONES VIEW <IP>` — probado el 2026-10-29 13:48 con `104.28.234.235`: da 1 (correcto: `CAP3781` y `kkjk` salen por IPs distintas de Cloudflare, `104.28.202.235` y `104.28.234.235`). Fallo de texto encontrado («1 conexiones»), corregido en `0628336` en los 14 idiomas con una frase sin plural («Conexiones simultáneas activas de la IP X: N»).
  - [x] `CONEXIONES LIST 2` con clones reales — probado el 2026-10-29 14:06: `CAP3781` y `kkjk` desde `46.39.209.77` (innovasur) → «2 — 46.39.209.77». Agrupa por IP real aunque el host visible sea un cloak (`Clk-8F0CA1B0…`).
  - [x] `LISTCLONES CAP3781` — probado el 2026-10-29 14:08: «Clones de CAP3781 (46.39.209.77), 2 conexiones: › kkjk (irc)».
  - [x] `BLOCK kkjk prueba` — probado el 2026-10-29 14:08. **Fallo encontrado**: la G-Line `*@46.39.209.77` (5h) se creó y se anunció en `#DeBuG`, pero los clientes **siguieron conectados**. Causa: con UnrealUdb, Ares publica la G-Line como registro UDB (`DB * INS … K::G::*@46.39.209.77`), que solo rechaza conexiones nuevas; el `op.mrc` original usaba `TKL`, que también echa a los conectados. **Corregido** en `3a6b465`: tras la G-Line, `KILLCLONES`/`BLOCK` hacen `KILL` a cada sesión del host (motivo «Killed (OPeR: <oper>): <motivo>»). Desplegado el 2026-10-29 14:11 (copia `~/backups/ares-data-20261029-141148.tar.gz`, anterior `/home/irc/ares-sitiochat.old-20261029-141148`, 7 bots, 0 errores).
  - [ ] Volver a probar `BLOCK`/`KILLCLONES` tras el arreglo (ahora lo hace `3dd1c77`) (antes quitar la G-Line de la prueba: `GLINE LIST` y `GLINE DEL <número>` de `*@46.39.209.77`; si no, responde «ya existe»).
  - [x] **`GLINE ADD` también desconecta** (decidido por el usuario el 2026-10-29, «Sí»): corregido en `3dd1c77`. El caso de uso común de G-Lines hace `KILL` (motivo «G-Lined (<oper>): <motivo>») de cada usuario conectado que coincide con la máscara, comparando el host **y la IP real**; así lo cubren `GLINE ADD`, `KILLCLONES` y `BLOCK` (se retiró el `KILL` propio de `3a6b465` para no matar dos veces). De paso se cerró un hueco: la protección de Root/IRCops con rol solo miraba el host, y un `GLINE ADD *@<IP de un Root>` pasaba; ahora también mira la IP. Desplegado el 2026-10-29 14:19 (copia `~/backups/ares-data-20261029-141942.tar.gz`, anterior `/home/irc/ares-sitiochat.old-20261029-141942`, 7 bots, 0 errores).
  - [ ] Probar en vivo `GLINE ADD *@<IP de prueba> 1h prueba` con un cliente conectado desde esa IP: tiene que caer en el momento.
  - [ ] `STATS`, `IRCOPS`, `LISTCLONES <nick>`
- [x] **Fallo encontrado en la prueba en vivo y corregido**: el log interno de OPeR registraba los 13 comandos nuevos (y `GEOIP`) como `UNKNOWN`, porque `OperServCommandLogSanitizer` tenía una lista fija de nombres. Corregido con test en `ee7c838` y **desplegado** el 2026-10-29 13:51 junto con `0628336` (copia `~/backups/ares-data-20261029-135155.tar.gz`, versión anterior `/home/irc/ares-sitiochat.old-20261029-135155`, parada limpia en 2 s, 0 migraciones, 7 bots presentados).
- [ ] Mejora posible: que esa lista se genere desde el registro de comandos de OPeR, para que no vuelva a quedarse atrás al añadir comandos (T4–T9 añadirán más; mientras tanto, añadirlos a mano en `OperServCommandLogSanitizer::COMMANDS` y en su test).

### 1.2. Por hacer

#### T4 — `JAIL` y `UMODE` (origen: pulsar `operserv/main.c:154`, `adminserv/main.c:51`)
- `JAIL <nick>`: saca al usuario de **todos** sus canales (SVSPART), le pone `+C` (SVSMODE) y lo mete en el canal cárcel (SVSJOIN).
  - Falta en Ares: acción de red para forzar la salida de un canal (SVSPART) en los **3 protocolos** (InspIRCd, UnrealStandalone, UnrealUdb); obtener los canales de un usuario; opción de configuración para el canal cárcel (p. ej. `ARES_JAIL_CHANNEL`).
  - Proteger Root e IRCops con rol.
- `UMODE <nick> +/-modos`: aplica modos de usuario con SVSMODE. Rechaza `r` y `h` («No puedes poner ni quitar los modos r y h»).
  - Ya existe `setUserMode` en los 3 protocolos.
- Permisos: `operserv.jail`, `operserv.umode` (en pulsar `UMODE` era de administrador).

#### T5 — `APODERA` y `DROPNDB` (origen: `op.mrc` :245, :272)
- `APODERA <#canal> [nick]`: cambia el fundador de un canal registrado al nick indicado o, si no se da, a quien lo ejecuta. Errores: canal no registrado, nick no registrado.
  - Cruza contextos: OperServ necesita un puerto propio hacia un `Port/In` público de ChanServ para cambiar el fundador (sin importar clases internas de ChanServ). En UnrealUdb debe republicarse el registro `C::` del canal.
- `DROPNDB <nick>`: borra un nick registrado de la base de datos. **Solo Root** (`c.r R` en `op.mrc`).
  - Revisar antes si el `DROP` de NickServ ya permite borrar nicks ajenos a un oper; si es así, decidir si `DROPNDB` es un alias o se descarta.
  - Debe negarse si el nick es fundador de canales (como `DROP`) o explicar qué pasa con ellos.
  - Riesgo: **borra datos** (riesgo alto, ítem 1). Requiere auditoría y test de que no borra nada si falla.

#### T6 — `SERVERMAP` (origen: pulsar `operserv/main.c:136`)
- Muestra el árbol de servidores enlazados (`+--` por nivel).
- Falta en Ares: un **registro de servidores** en el contexto Irc, alimentado por SERVER/SID/SQUIT en los 3 protocolos (hoy solo existe la configuración del enlace propio y el `serverSid` de cada usuario). Es el bloque más delicado de T4–T10 porque toca el parseo de protocolo.
- Permiso: `operserv.servermap` (en pulsar, administrador).

#### T7 — `ELINE` (origen: pulsar `adminserv/elines.c`)
- `ELINE ADD [+expiración] <user@ip|ip> <motivo>`: excepción de baneo por IP (el host debe ser una IP válida). Duplicados rechazados.
- `ELINE DEL <máscara|número|lista 1,3-5>`, `ELINE LIST`, `ELINE VIEW` (con quién, cuándo y expiración), `ELINE COUNT`, y `ELINE CLEAR` (en pulsar existe).
- Se envía a la red como `TKL + E` / `TKL - E`.
- Falta en Ares: entidad + mapeo Doctrine XML + **migración** (probada en sqlite, mysql, mariadb y postgres, que es lo que hace el CI), repositorio, acción de red TKL E en los 3 protocolos (o restringirlo a Unreal si InspIRCd no lo soporta), expiración automática.
- Riesgo alto (ítem 1: migración y formato persistido).

#### T8 — `SPAM` (origen: `op.mrc` :404)
- `SPAM LIST`, `SPAM ADD <patrón> <acción> [motivo]`, `SPAM DEL <patrón>`.
- Acciones válidas en `op.mrc`: `gline`, `gzline`, `shun`, `kill`, `block`, `warn`, `tempshun` (también `kline`, `zline`, `dccblock`, `viruschan` en el original; decidir cuáles se aceptan).
- En UnrealUdb se publica como `K::F::b64:<base64(patrón)>::{targets,action,ban-time,reason,expires,match-type}`; en los otros protocolos, decidir (spamfilter por TKL F o no soportado).
- Falta: persistencia de las reglas (migración) y proyección UDB.
- Riesgo alto (ítem 3: formato UDB que consume el IRCd).

#### T9 — `SET` y `SETTIME` (origen: `op.mrc` :341, :324)
- `SET SUFIJO|NICKSERV|CHANSERV|IPSERV|PASSPROTECT|PREFIJOS <valor>` → claves UDB `S::J`, `N`, `C`, `I`, `F`, `P`.
  - Validaciones: sufijo solo letras; NICKSERV/CHANSERV/IPSERV con forma `nick!ident@host`; PASSPROTECT `intentos:segundos` (p. ej. `3:60`); prefijos no vacío.
  - Solo Root. Solo tiene sentido con el protocolo UnrealUdb.
  - Comprobar antes si Ares ya gestiona el bloque `S` de UDB desde la configuración (`.env`), para no tener dos fuentes que se pisen.
- `SETTIME`: resincroniza la hora de la red (`TSCTL SVSTIME`). Solo Root. Acción nueva en los protocolos Unreal.
- Riesgo alto (ítem 3).

#### T10 — Controles finales
- Suite completa con cobertura, PHPStan, deptrac, CS-Fixer en Linux; actualizar `HELP` de OPeR y la documentación.

### 1.3. Decisiones tuyas pendientes

- [ ] **Comandos no portados** porque Ares ya los cubre. ¿Se quedan fuera?
  - `UNGLINE` → ya es `GLINE DEL <máscara|número>`.
  - `VIEWIP` → ya es `USERIP <nick>` de NiCK.
  - `RENAME` → ya es `RENAME <nick>` de NiCK (nick aleatorio; el de dbots permitía elegir el nick nuevo).
  - `ROOT <contraseña>` → Ares define los Root por cuenta en la configuración; una contraseña compartida sería menos segura.
- [ ] ¿Hay algún comando de T4–T9 que quieras **quitar**?
- [ ] `KILLCLONES`/`BLOCK` dejan **dos registros de auditoría** (el del `GLINE ADD` interno y el del comando). ¿Vale así o se deja uno?
- [ ] **Motivo** en `KILLCLONES`/`BLOCK`: `op.mrc` lo exigía; ahora hay uno por defecto (`Clones of <nick>` / `Blocked by <bot>`). ¿Obligatorio?
- [ ] ¿Los **bots de servicio** deben contar en `STATS` y salir en `IRCOPS`? (no comprobado).
- [ ] En `UMODE`, ¿solo administradores (como pulsar) o cualquier IRCop con el permiso?

---

## 2. Servicios de dbots que Ares no tiene

Tareas T7–T16 de `odd/tasks/migrate-missing-services.md` (todas pendientes). Cada servicio nuevo es un bot pseudo-cliente propio con su contexto en `src/<Servicio>/`, registro de comandos, `HELP`, traducciones en 14 idiomas, permisos y tests.

### 2.1. CeNTeR (origen: `ce.mrc`)
- [ ] Escala de staff: `PREOPER ADD|DEL <nick>`, `OPER ADD|DEL <nick>` (Administrador), `ADMIN ADD|DEL <nick>` (Root), `REMOPER <nick>` (Root, quita cualquier rango), `ROOT ADD|DEL <nick>`.
- [ ] `MODULO LIST|LOAD|UNLOAD [nombre]` (Root): carga y descarga módulos en caliente (necesita §2.5).
- [ ] `RAW <línea>` y `NORAW ADD|DEL|LIST <comando>` (Root).
- Decisión previa: Ares ya tiene `IRCOP` y `ROLE` en OPeR (roles y permisos). Hay que decidir si CeNTeR es una **fachada** con los nombres del original sobre ese sistema, o un sistema de rangos aparte. Recomendado: fachada, para no tener dos fuentes de verdad.
- `KILL`/`GLINE` se quedan en OPeR; `CLONES` va a CLoNeS.

### 2.2. CLoNeS
- [ ] `PERM <IP|host> <N>`: excepción permanente (`allow` con `maxperip N`).
- [ ] `ADD <IP|host> <N> <duración>` (`30d`, `6m`, `1y`), `RENEW`, `EDIT`, `DEL`, `INFO [IP|host]`, `LIST`.
- Necesita persistencia (migración) y cómo se aplica en el IRCd (bloque `allow` / UDB).

### 2.3. NoServ (origen: `no.mrc`)
- [ ] `ALTA` / `BAJA` (suscripción de la cuenta a noticias), `EMAIL <mensaje>` (envía a todos los suscritos).
- [ ] `MOTD ADD|DEL|LIST`, `NICK ADD|DEL|LIST <nick> [mensaje]`, `REPRESENTANTES ADD|DEL|LIST`, `NOTICIAS` (lee una URL configurable), `SERVIDOR <servidor> [mensaje]`.
- Ojo: Ares ya tiene `MOTD` en OPeR; decidir cómo conviven.

### 2.4. DeBuG (origen: `de.mrc`) — **siguiente tarea, a hacer desde el PC Linux**

Petición del usuario (2026-10-29): *«`C:\IRC\services\sistema\de.mrc` --> Intenta portarlo con los mismos mensajes, etc. Pero a lo nuevo y actualiza el documento para portarlo al PC Linux y continuar desde ahí»*.

Es decir: **mismos mensajes (texto y colores) que `de.mrc`**, pero implementado «a lo nuevo», dentro de Ares y con su arquitectura. No hace falta una segunda conexión S2S como en el original: Ares ya ve todo el tráfico de la red.

#### Cómo funcionaba el original
- `de.mrc` abría una **segunda conexión de servidor** (`debug.server`) contra el IRCd. El bot `$debugserv` entraba en el canal de debug (`$l.conf(debugserv,canal.debug)`) con `+qntis`, ponía el topic «Canal de debug de la red» y hacía ping cada 90 s.
- Cada línea recibida pasaba por un `if/elseif` y, según el comando, escribía en el canal con `debugc` (NOTICE/PRIVMSG del bot al canal).
- Las líneas `DB INS/DEL` de UDB las trataba aparte (sincronización de base de datos, no se mostraban).
- Lo que no reconocía lo volcaba en bruto con el prefijo `<><><>`.

#### Mensajes a reproducir (códigos de color mIRC: `\x03NN` = color, `\x02` = negrita; 12 = azul, 4 = rojo, 3 = verde, 6 = morado)

| Evento de red | Mensaje de `de.mrc` (colores entre paréntesis) |
|---|---|
| Conexión de usuario (NICK/UID) | `(3)Conecta:(12) NICK: <nick> (12)IDENT: <ident> (12)IP/HOST: <host> (12)REALNAME: <realname> [(12)MODOS: <modos>] (12)SERVIDOR: <servidor>` |
| Desconexión (QUIT) | `(4)Desconecta:(12) NICK: <nick> (12)( <mensaje> (12))` |
| Cambio de nick | `(12)<nick> cambia de nick a(12) <nuevo>` |
| JOIN (un canal / varios) | `(12)<nick> entra al canal(12) <canal>` / `(12)<nick> entra a los canales(12) <canales>` |
| PART (con/sin mensaje, uno/varios) | `(12)<nick> sale del canal(12) <canal> con el mensaje: <msg>` / `… sale del canal(12) <canal>` / `… sale de los canales(12) <canales> con el mesanje: <msg>` (sic, «mesanje» en el original) / `… sale de los canales(12) <canales>` |
| MODE de canal | `(12)<origen> cambia modos de(12) <canal> a(12) <modos y parámetros>` (sin el TS final cuando lo envía un servidor) |
| MODE / UMODE2 de usuario | `(12)<nick> cambia modos a:(12) <modos>` / `(12)<nick> cambia modos a(12) <modos>` |
| SVS2MODE | `(4)Modos forzados en(12) <nick>:(12) <modos>` |
| TOPIC (pone / quita) | `(12)<nick> cambia el topic de(12) <canal> por: <topic>` / `(12)<nick> quita el topic de(12) <canal>` |
| TOPIC de servidor | `(12)TOPIC del canal(12) <canal>: <topic>` |
| KICK | `(12)<nick> expulsa a(12) <víctima> del canal(12) <canal>, motivo: <motivo>` |
| KILL | `(12)<origen> (4)KILLea a(12) <víctima>: <motivo sin paréntesis>` (se ignora el KILL de colisión `(?)`) |
| AWAY (se ausenta / cambia / vuelve) | `(12)<nick> se ausenta, mensaje:(12) <msg>` / `(12)<nick> Cambia el mensaje de away:(12) <msg>` / `(12)<nick> deja de estar ausente tras(12) <duración en castellano: semana/dia/hora/minuto/segundo>.` (requiere recordar desde cuándo está ausente) |
| SERVER (entra) | `Servidor(12) <nombre> (6)(<descripción>(6)) entra en la red.` |
| SQUIT (sale) | `Servidor(12) <nombre> sale de la red: <motivo>` |
| SETHOST | `Cambio de (12)VHOST sobre(12) <nick>: <vhost>` |
| CHGHOST / CHGIDENT / CHGNAME | `(12)<oper> usa (12)CHGHOST sobre(12) <nick>: <host>` (idem `CHGIDENT`, `CHGNAME`) |
| SETNAME | `(12)<nick> usa(12) SETNAME: <realname>` |
| SWHOIS (pone / quita) | `(12)<origen> pone un (12)SWHOIS a(12) <nick>: <texto>` / `(12)<origen> (4)quita el (12)SWHOIS a(12) <nick>` |
| CHATOPS / ADCHAT / NACHAT | `(12)<nick> envia un CHATOPS (6)(Mensaje a todos los OPERs(6)) con el mensaje: <msg>` (ADCHAT: «a todos los ADMINs», NACHAT: «a todos los NET-ADMINs») |
| SENDSNO `o` (se hace IRCop / intento fallido) | `(12)<nick> es ahora(12) <tipo de oper, paréntesis en rojo>` / `(4)<nick> intentó identificarse como IRCop...` |
| SENDSNO `G` (tempshun) | `(4)TEMPSHUN puesto en(12) <máscara> por(12) <oper>: <motivo>` / `Elimitando (4)TEMPSHUN puesto a(12) <máscara> por(3) <oper>` (sic) |
| SENDSNO `e` | `(4)<…> de(12) <…>: <…>` |
| SENDSNO `S` (spamfilter) | lo envía **OPeR al canal de opers** (no a DeBuG): `(4)SPAM detectado desde(12) <nick>: <texto con la palabra en rojo>` |
| Otro SENDSNO | `(12)<remitente> <texto>` |
| Cualquier otra línea no reconocida | `<><><> <línea en bruto>` |
| Se ignoran | PRIVMSG, NOTICE, PING/PONG, WHOIS, PROTOCTL, ADMIN, INFO, VERSION, PASS, SMO, EOS, TKL, GLOBOPS, DBQ, DB, NETINFO y numéricos |

**`HELPOP` (rama `help`)**: no es del canal de debug; es una ayuda de **HeLP**. Si alguien pide `/helpop <tema>` y el tema existe en la ayuda del IRCd (`USERCMDS`, `OPERCMDS`, `SVSCMDS`, `UMODES`, `SNOMASKS`, `CHMODES`, `OFLAGS` o cualquier comando listado en `de.mrc` líneas ~160-178), HeLP le contesta «He visto que pides ayuda sobre el tema(12) X, para ver la ayuda has de poner (4)? delante:» y «/helpop (4)?(12)X». Si no existe, avisa a `@#opers_help` («(12)<nick> necesita ayuda sobre:(12) <tema>») y al canal de opers («(12)<nick> está buscando ayuda sobre(12) <tema> (6)( /helpop <tema> (6))»). Decidir con el usuario si se porta dentro de esta tarea o como mejora de HeLP.

#### Cómo hacerlo «a lo nuevo» en Ares
- **Nuevo bot de servicio `DeBuG`** (pseudo-cliente propio, como HeLP o GlobalServ), en un contexto pequeño propio o como adaptador de `Irc`. Lo decide quien lo implemente leyendo `.agents/architecture.md`; la regla es no meter lógica de presentación en `Irc/Domain`.
- **Fuente de eventos**: los eventos publicados del contexto `Irc` (`src/Irc/Application/PublishedEvent/*` y `src/Irc/Domain/Event/*`: `UserJoinedNetworkEvent`, `UserQuitNetworkEvent`, `UserNickChangedEvent`, `UserModeChangedEvent`, joins/parts, topic, kick, kill…). Para lo que no tenga evento todavía (AWAY, SETNAME, CHGHOST, SWHOIS, CHATOPS, SENDSNO, SERVER/SQUIT), añadir eventos en los **3 protocolos** o, si solo existe en UnrealIRCd, en `UnrealUdb` y `UnrealStandalone` por separado (sin compartir código entre ellos, ver `AGENTS.md` §7).
- **Reutilizar lo que ya existe**: `DebugChannelJoinSubscriber` (une los bots al canal de debug), `IrcopsDebugChannelProtectionSubscriber`, los `*DebugNotifier` de cada servicio y `OperServ/Adapter/Out/Irc/DebugChannelCommandAuditSink` (ya escribe en `#DeBuG` las acciones de los servicios, por ejemplo «JuanJo_Jaen ejecuta el comando GLINE ADD sobre…»). Buscar con `grep -rni "debug_channel\|debugChannel" src config` dónde se configura el canal.
- **Textos**: los mensajes van en las traducciones (dominio propio, 14 idiomas); el español debe ser **idéntico** al de la tabla (incluidos colores; los dos «sic» se pueden corregir a «mensaje» y «Eliminando» si el usuario lo aprueba). Los mensajes del canal de debug van en el idioma de la red (es), no en el de cada usuario.
- **Seguridad**: nunca volcar contraseñas ni argumentos de `REGISTER`, `IDENTIFY`, `DROP`, `SET` o `ROOT`, ni valores `N::*::pass` o blobs Base64 largos. No volcar el tráfico de reconciliación/sincronización inicial (un burst inundaría el canal): empezar después del fin de sincronización.
- **Volumen**: en una red con mucho tráfico, JOIN/PART/QUIT pueden ser cientos por minuto. Prever un interruptor por tipo de evento en la configuración (`.env`) y, como mínimo, poder desactivar el relay entero.
- **Tests**: un test por tipo de evento que compruebe el texto exacto (con colores) y uno de que lo ignorado no se escribe; tests de que no se filtran secretos; HELP y traducciones según `AGENTS.md`.
- **Riesgo**: ítem 2 (puede filtrar datos sensibles al canal si se olvida una redacción) e ítem 6 (volumen). Pide revisión.

#### Pasos
- [ ] Mapear en Ares qué eventos existen ya y cuáles faltan por protocolo (tarea de exploración).
- [ ] Decidir con el usuario: ¿HELPOP dentro o fuera?, ¿corregir los dos «sic»?, ¿qué eventos activados por defecto?
- [ ] Crear el documento de seguimiento `odd/tasks/debug-relay.md` con los mensajes de la tabla copiados literalmente como specs.
- [ ] Implementar por bloques pequeños (≤ ~900 líneas por commit), cada uno en verde, en `main`.
- [ ] Desplegar con `scripts/vps-deploy.sh` y probar en vivo en `#DeBuG`.

### 2.5. Módulos
- [ ] Mecanismo de módulos cargables (pseudo-clientes que reciben los mensajes parseados), gestionado desde `CeNTeR MODULO`.
- [ ] **Sumidero**: anti-spam/anti-clones; entra en `#Sumidero` y canales vigilados, aplica shun a no identificados tras un retardo, hunde a los que hacen flood, tokens de verificación (`VERIFICAR <token> <ip>`), comandos de staff (`help`, `status`, `release`, `sink`, `ignore`, `unignore`, `spam`).
- [ ] **Ping**: pseudo-cliente simple de ping/pong.

---

## 3. Huecos en servicios que ya existen

### 3.1. CHaN (referencia: `ch.mrc` y `HANDOFF-migracion-dbots-ares.md` §4)
- [ ] `SET RESTRICTED`, `SECUREOPS`, `SECUREHALFOPS`, `DEBUG`, `AUTOLIMIT` (+ `PBANS`, `PMODE` de dbots). Por cada opción: columna + **migración** + proyección + handler + reactor real. *No publicar opciones que no hagan nada.*
  - `SECUREOPS`/`SECUREHALFOPS` se enganchan en la reconciliación de rangos al `SJOIN` (`RankReconciliationPolicy`).
  - `AUTOLIMIT` calcula el límite y aplica `+l`.
  - `DEBUG` es registro por canal.
- [ ] `CLEAR <#canal> MODES|BANS|OPS|HALFOPS|VOICES|TOPIC` (hoy solo `CLEARUSERS` y `CLEARACCESS`). Sin migración: reutiliza `ChannelServiceActionsPort` y `ChannelView::members`/`bans`.
- [ ] `AKICK <#canal> ENFORCE`.
- [ ] `TOPIC <#canal> <texto>` como comando propio (hoy es `SET TOPIC`).
- [ ] Decidir `BFOUNDER` (fundador por bot en `ch.mrc`) y `ESPECIAL` (bots de entrada; probablemente descartar).
- No aplica: contraseñas de canal (`IDENTIFY`, `SET PASSWORD`, `GETPASS`), porque el fundador es la cuenta de NiCK.

### 3.2. NiCK (referencia: `ni.mrc`)
- [ ] `SWHOIS <nick> <texto>` (oper; UDB `N::<nick>::swhois`).
- [ ] `SENDPASS <nick>` (oper; contraseña temporal por memo) y `SENDNEWPASS <nick>` (oper; contraseña temporal por email).
- [ ] `AUTOMODE <nick> [modos]` (modos automáticos al identificarse con `+r`).
- [ ] `SET URL`, `SET QUIT`, `SET KILL`, `SET AUTOMODE` (Ares tiene `PASSWORD`, `EMAIL`, `LANGUAGE`, `TIMEZONE`, `PRIVATE`, mensaje y `VHOST`).
- [ ] `LISTSUSPEND` / `LISTFORBID` de nicks (comprobar si el `LIST` de Ares ya filtra por estado).

### 3.3. CReG (referencia: `cr.mrc`)
- [ ] `LIBERA <#canal>`: libera una solicitud denegada para que se pueda volver a pedir.
- [ ] `ADMIN ADD|DEL|LIST [nick]`: quién puede aprobar registros.
- [ ] `OFICIAL <#canal> [descripción]`: registra un canal de la red.
- [ ] `COMERCIAL <#canal> <fundador> [descripción]`: registra un canal a nombre de otro (comparar con el `SAREGISTER` actual).
- [ ] `INFO <#canal>` (oper).

### 3.4. MeMo (referencia: `me.mrc`)
- [ ] `SET NOTIFY ON|LOGON|NEW|OFF` (cuándo avisar de memos sin leer). `NOMEMOS` parece equivaler al `IGNORE` de Ares: comprobar.

---

## 4. Infraestructura y mantenimiento

### 4.1. Despliegue en el VPS
- [ ] **Arranque automático**: hoy Ares se lanza a mano (`cd /home/irc/ares-sitiochat && nohup setsid php bin/console irc:connect -vv >> var/ares.log 2>&1 &`). Crear un servicio systemd (de sistema o de usuario `irc` con `loginctl enable-linger`) con reinicio automático, para que sobreviva a reinicios y caídas.
- [ ] **Procedimiento de despliegue documentado** (el que se usó el 2026-10-29):
  1. En Windows: `git -c core.autocrlf=false -c core.eol=lf archive` del commit (sin esto los archivos llegan con CRLF y se rompe todo).
  2. `scp` a `~/deploy-staging`, extraer en una carpeta nueva y copiar `.env.local` y `vendor/` (si `composer.lock` no cambió; si cambió, `composer install --no-dev`).
  3. `composer dump-autoload -o` y `php bin/console lint:container`.
  4. Parar Ares **apuntando al PID de `php`**, no al `bash` que lo envuelve: `kill -TERM $(pgrep -f "^php bin/console irc:connect")` (así para limpio en ~2 s; con un patrón sin `^` se mata el `bash` y hay que forzar).
  5. Copia de `var/data` y `.env.local` en `~/backups/`.
  6. Intercambiar carpetas, copiar `var/data`, `var/log`, `var/ares.log`.
  7. `cache:clear --env=prod`, `doctrine:migrations:status` (y `migrate` si hay nuevas), arrancar y revisar `var/log`.
  - **Hecho**: el procedimiento está en el repo como **`scripts/vps-deploy.sh <commit>`**: empaqueta el commit, lo sube y ejecuta en el VPS todos los pasos de arriba. Ejecuta `composer install` solo si cambió `composer.lock`, y al final informa de bots presentados y errores. Requiere el alias SSH `vps-irc`.
- [ ] **Limpiar el VPS**: `~/deploy-staging` (ya borrados los paquetes antiguos `a1e6644` y `c7dc489`; quedan `a1e6644.tar.gz` y `8d68349.tar.gz`), `/home/irc/ares-sitiochat.old-20261027-053051`, `/home/irc/ares-sitiochat.old-20261029-075953` `/home/irc/ares-sitiochat.old-20261029-133118` y, cuando `0628336` esté estable, `/home/irc/ares-sitiochat.old-20261029-135155`. Rotar `var/ares.log`.

### 4.2. CI (GitHub Actions)
- [ ] El job **Deploy Ares IRC Services** espera un runner Docker `ares-deploy` que no existe: se queda en cola en cada push (hay runs en cola desde el 2026-10-28). Opciones: quitar el job, o cambiarlo por un despliegue por SSH al VPS con una clave de despliegue en los secretos del repo.
- [ ] Cancelar los runs que llevan días en cola.
- [ ] Aviso de GitHub: `ubuntu-latest` pasa a Ubuntu 26 desde el 19-10-2026; vigilar que el CI siga funcionando.

### 4.3. Seguridad
- [ ] Borrar la clave privada `C:\IRC\Code-Sessions\irc_vps_key` (ya está en `~/.ssh/irc_vps_key`).
- [ ] Cambiar la contraseña del usuario `irc` del VPS (dijiste que lo harías; el acceso por clave no depende de ella).
- [ ] Revisar periódicamente `composer audit` (el CI lo hace en cada push y para el pipeline si hay avisos).

### 4.4. Otros repositorios y entorno
- [ ] **dbots-services**: `/opt/dbots-services` en el PC Linux tiene 7 commits sin subir a GitHub (identificación por SVSLOGIN, tests de integración en vivo…). Decidir si se suben o si dbots queda archivado.
- [ ] **Windows**: la suite completa no corre (falta `pcntl`) y 2 tests de `LayerDependencyTest` fallan por los separadores de ruta (`\` frente a `/`). Opciones: arreglar esos 2 tests para que sean independientes del sistema, o hacer siempre los controles completos en Linux.
- [ ] El clon de Windows tiene `core.filemode=false` (para no marcar como cambiados los scripts por permisos). Mantenerlo.
- [ ] Actualizar `HANDOFF-migracion-dbots-ares.md` también en el VPS en el próximo despliegue.

---

## Referencias

En el PC Linux, los scripts originales de mIRC están en `/home/juanjo/irc/sitiochat/sistema/` (allí los usó la sesión de opencode; `ch.mrc`, `gl.mrc`…). En Windows están en `C:\IRC\services\sistema\`. pulsar, atheme y botshispanobdd están en `C:\IRC\services\` de Windows; si hacen falta en Linux, copiarlos o pedírselos al usuario.

| Recurso | Ruta |
|---|---|
| Servicios originales (mIRC) | `C:\IRC\services\sistema\*.mrc` (`op`, `ch`, `ni`, `me`, `cr`, `ce`, `gl`, `he`, `no`, `de`, `pr`) |
| pulsar (C) | `C:\IRC\services\pulsar\modules\operserv`, `modules\adminserv` |
| dbots-services (PHP) | `github.com/juanjiyo/dbots-services` (y `/opt/dbots-services` en el PC Linux) |
| IRCd | `C:\IRC\obbyircd` (la red usa UnrealIRCd 6.2.7 + `third/udb` 4.0.0-rc1 + `third/sacmds` 2.2.3) |
| Acceso al VPS | `ssh vps-irc` (`irc@5.250.189.222`, clave `~/.ssh/irc_vps_key`) |
| Seguimiento OPeR | `odd/tasks/operserv-dbots-parity.md` |
| Anatomía de un comando de OPeR | Anexo C de este documento |
| Especificación de los comandos de OPeR | Anexo B de este documento |
| Entorno, trampas y preferencias | Anexo A de este documento |
| Transcripts de sesiones anteriores | `C:\IRC\Code-Sessions\*.md` |
| Seguimiento servicios | `odd/tasks/migrate-missing-services.md` |
| Traspaso general | `HANDOFF-migracion-dbots-ares.md` |

---

# Anexos

## Anexo A — Entorno, trampas conocidas y preferencias del usuario
Datos para la IA que continúe. Todo está comprobado el 2026-10-29.

### Preferencias del usuario (obligatorias)

- Hablarle **siempre en castellano neutro, sin voseo** («quieres», no «querés»). El código, los commits y la documentación técnica van en inglés, salvo que pida otra cosa.
- **Commits**: Conventional Commits en inglés, **sin** `Co-Authored-By` ni ninguna mención a una IA.
- No usa **Docker**, ni en el PC ni en el VPS.
- Hacer **una sola pregunta** cada vez y esperar la respuesta.
- No dar por buena una afirmación sin comprobarla en el código o en los documentos.
- Ha pedido «todo en la rama main» para el repo. Antes de subir algo o hacer merge a `main`, confirmar con él.

### Máquinas

| Máquina | Qué es | Ruta de Ares |
|---|---|---|
| Windows (este) | Equipo principal de trabajo | `C:\IRC\services\ares-sitiochat` |
| PC Linux Mint | Donde trabajaba con opencode/DeepSeek | `/opt/ares` (remotos `origin` = ares-irc-services, `sitiochat` = ares-sitiochat). **No es el VPS** |
| VPS Ubuntu `5.250.189.222` | Producción | `/home/irc/ares-sitiochat` (usuario `irc`) |
| `217.160.136.43` | Otro servidor (aparece en `known_hosts`) | — |

- Acceso al VPS desde Windows: `ssh vps-irc`. El alias está en `~/.ssh/config` y usa la clave `~/.ssh/irc_vps_key` (ed25519, sin contraseña). **Nunca** guardar contraseñas.
- En el VPS, Ares **no es un checkout de git**: es una copia de archivos. El repo es privado y el VPS no tiene credenciales de GitHub, así que el código se lleva con `git archive` + `scp` (ver §4.1).
- En el VPS, el proceso `messenger:consume async async_emails` es hijo de `irc:connect`: se reinicia con él.
- Los transcripts de sesiones anteriores con opencode están en `C:\IRC\Code-Sessions\*.md`. En la sesión `6a2e0470…`, DeepSeek **nunca se conectó al VPS**: dio por hecho que `/opt/ares` era el VPS.

### Git y repositorio

- `main` = `b9076d3` (local y GitHub).
- `feature/operserv-dbots-parity` = `16a2ca5`: rama original de T1–T3, en 4 commits grandes.
- `feature/operserv-dbots-parity-sliced` = `ea65ebf`: **la buena**. Son 17 commits pequeños (máximo 785 líneas cada uno) y el árbol final es idéntico al de `16a2ca5`. **Ninguna de las dos ramas está subida.**
- El clon de Windows tiene `core.autocrlf=true` y `core.filemode=false`. Así no salen como modificados los 8 scripts que solo cambian de permisos.

### Trampas en Windows

1. **La suite completa no corre**: falla con `Call to undefined function pcntl_async_signals()`, y salen avisos de `unlink` en los tests de snapshots UDB. Solo se pueden ejecutar tests concretos:
   `XDEBUG_MODE=off php vendor/bin/phpunit --no-coverage tests/OperServ tests/Shared tests/Architecture tests/Bootstrap`
   La suite completa con cobertura (`./scripts/check-coverage.sh 100 --issues`) solo se puede ejecutar en Linux: en el CI, en el VPS o en el PC Linux.
2. Dos tests de `tests/Architecture/LayerDependencyTest.php` fallan **solo en Windows**, por los separadores de ruta: `sharedKernelContainsOnlyApprovedGenericContracts` y `testNamespacesFollowTheirBoundedContextPaths`. También fallan en `main`, así que no los ha roto este trabajo.
3. **Xdebug** está activo. Hay que exportar `XDEBUG_MODE=off` en todo lo que no sea cobertura; si no, PHPUnit pide `XDEBUG_MODE=coverage` y no ejecuta nada.
4. **`git archive` con autocrlf** genera CRLF. Para el VPS hay que usar `git -c core.autocrlf=false -c core.eol=lf archive …`.
5. **php-cs-fixer** reescribe los finales de línea de muchos archivos que no tienen nada que ver. Después de ejecutarlo, restaura con `git checkout -- <archivo>` los que tengan `git diff --ignore-cr-at-eol` vacío, y commitea solo lo tuyo.
6. La configuración de php-cs-fixer convierte `assertEquals` en `assertSame`. Por eso, en los tests compara valores escalares, no objetos de valor.
7. Los heredocs de Bash rompen el contenido PHP (las `\` de los namespaces). Usa la herramienta Write para crear archivos.
8. Si `vendor` es un enlace (junction) en un worktree, PHP carga el `src` del destino. En un worktree hay que copiar `vendor`, no enlazarlo.
9. `php bin/console lint:container` y los tests del kernel regeneran el `config/reference.php` versionado. Restáuralo antes de hacer `git add`.
10. **Composer** propone saltos de versión mayor (por ejemplo `symfony/mime` 7.4 → 8.1). Fija la rama con `composer update <paquete> --with '<paquete>:^7.4.x'`.

### Trampas de Ares (código)

- Deptrac **prohíbe** que los adaptadores de OperServ importen `App\Irc\Domain`. Por eso la decodificación de la IP en `ipBase64` está copiada, con la misma forma, en `NetworkUserMapper`.
- `ManageGlineHandler` crea su propio registro de auditoría `GLINE ADD`: cualquier comando que lo use deja dos registros.
- `OperServContext::reply()` añade los marcadores del nick del servicio. En los tests, comprueba cada `%param%` por separado.
- `ServiceUidRegistry::getUid('operserv')` devuelve el UID del bot OPeR.

### CI (GitHub Actions, `.github/workflows/ci.yml`)

- Se lanza en `push` a `main` y en `pull_request` a `main`. **Las ramas feature no lanzan CI** si no se abre un PR.
- Jobs:
  - `PHP 8.5 — Lint, CS & Tests` (unos 9 min): `composer audit`, PHPStan, deptrac, tests y cobertura al 100%.
  - 4 de migraciones (mysql, mariadb, postgres, sqlite).
  - `Deploy`, que nunca se ejecuta: espera un runner Docker que no existe.
- `composer audit --locked` para el CI si aparece cualquier aviso de seguridad nuevo, aunque no tenga que ver con tu cambio. Así pasó el 2026-10-29 con CVE-2026-45070.

### Revisión con Gentle AI (`gentle-ai`, versión 4.3.1, solo en este Windows)

- La revisión está activada en el repo. El hook de parada recuerda los cambios sin revisar.
- **Límite de tamaño:** un cambio de unas 3850 líneas se rechazó con `lens_context_budget_exceeded`. Uno de 1529 líneas sí se preparó. Por eso se trabaja en bloques de unas 1500 líneas.
- **El consentimiento lo da el usuario, uno por cada bloque.** No se puede dar en su nombre. El bloque A está preparado y pendiente: los detalles están en §1.1.
- **Engram** (memoria persistente): al guardar avisa de que el proyecto es ambiguo (`ares` / `codegraph`). El usuario eligió **`ares`**.

### Herramientas instaladas en Windows

PHP 8.5.11 (scoop, con Xdebug, sin pcntl), Composer 2.10, Git Bash, `gh` (con sesión iniciada para `juanjiyo`), `gentle-ai`, Python.

---

## Anexo B — OPeR: especificación de los comandos a portar
Mapa verificado el 2026-10-29 leyendo el código. Sirve para implementar lo que falta (T4–T9) y para comprobar lo ya hecho (T1–T3).

### Fuentes, por orden de prioridad

1. **`C:\IRC\services\sistema\op.mrc`**: el OPeR original en mIRC. Manda en el comportamiento y en el texto en español. En el código, `c.r O` significa rango Operador y `c.r R` significa Root.
   - Despachador: `operserv.procesa`, :5
   - Ayuda: `operserv.ayuda`, :31
   - Comandos: `stats` :43, `ircops` :67, `op` :89, `deop` :96, `mode` :103, `kick` :110, `kill` :117, `block` :127, `gline` :143, `root` :228, `apodera` :245, `limpia` :254, `rename` :263, `dropndb` :272 (Root), `killclones` :280, `listclones` :296, `settime` :324, `invite` :331, `set` :341 (Root), `spam` :404
2. **pulsar** (servicios en C). Manda en `JAIL`, `UMODE`, `ELINE`, `SERVERMAP`, `CONEXIONES` y `VIEWIP`, que **no están** en `op.mrc`.
   - `C:\IRC\services\pulsar\modules\operserv\main.c`: `STATS` :134, `SERVERMAP` :136, `KILLCLONES` :149, `LISTCLONES` :151, `JAIL` :154, `VIEWIP` :164
   - `C:\IRC\services\pulsar\modules\operserv\sessions.c`: `CONEXIONES` :102
   - `C:\IRC\services\pulsar\modules\adminserv\main.c`: `SERVERMAP` :48, `UMODE` :51, `SETTIME` :55. En pulsar, AdminServ es **solo para administradores**.
   - `C:\IRC\services\pulsar\modules\adminserv\elines.c`: `ELINE`, completo (`ADD`/`DEL`/`CLEAR`/`LIST`/`VIEW`/`COUNT`, con listas de números `1,3-5`)
3. **dbots-services** (port en PHP, `github.com/juanjiyo/dbots-services`). En `src/OperServ/Adapter/In/Irc/OperServCommandHandler.php` (abreviado **H**) y `src/OperServ/Adapter/Out/Irc/S2sEnforcement.php` (abreviado **E**). Sus respuestas están **en inglés**; solo la ayuda está en español. Úsalo para ver qué envía exactamente a la red, no para copiar los textos.

### Mecánica común en dbots (orientativa)

- **Niveles de permiso:**
  - oper: el usuario tiene `+o`
  - admin: rango CeNTeR Administrador (nivel ≥ 7)
  - root: sesión `ROOT <contraseña>` abierta
- **Fallo de cableado en dbots:** en `DaemonFactory.php:337-352` no se pasan `elines`, `staff` ni `resolver`. Por eso `ELINE`, `SERVERMAP` y `CONEXIONES` solo funcionan como ROOT, y `ELINE` responde siempre «repository not available». **No copiar este fallo.**
- **En dbots, `STATS` e `IRCOPS` no comprueban permisos.** En Ares se hicieron solo para IRCops.
- **Duraciones:** `0` significa permanente; un número sin sufijo son minutos; los sufijos son `s`, `m`, `h`, `d` y `w`.

En Ares, los permisos son por rol (`operserv.<x>`) y Root se define por cuenta en la configuración. Ver el Anexo C.

---

### Ya implementado en Ares (T1–T3) — para comprobarlo

| Comando | dbots | Qué envía a la red | En Ares |
|---|---|---|---|
| `KICK <#canal> <nick> [motivo]` | H:178-205 | `SAKICK <canal> <uid> :<motivo>`; motivo por defecto «Expulsado por OPeR» | `kickFromChannel`, permiso `operserv.kick`; protege Root e IRCops con rol; el canal debe existir y el nick tiene que estar dentro |
| `OP` / `DEOP <#canal> <nick>` | H:208-225 | `SAMODE <canal> +o/-o <nick>` | `setChannelMemberMode`, permiso `operserv.op`; `DEOP` protege igual que `KICK` |
| `MODE <#canal> <modos> [args]` | H:228-245 | `SAMODE <canal> <modos> [arg]` | `setChannelModes`, permiso `operserv.mode`; valida `^[+-]…` |
| `LIMPIA <#canal>` | H:926-944 | `SAMODE <canal> -imnpst` | permiso `operserv.mode` |
| `INVITE <#canal>` | H:1062-1086 | `INVITE <nick del que ejecuta> <canal>` | `inviteUserToChannel`, permiso `operserv.invite`; se niega si ya estás dentro |
| `STATS` | H:1041 | «Users online: N» | solo IRCop |
| `IRCOPS` | H:1046 | lista de usuarios con `+o` | solo IRCop |
| `LISTCLONES <nick>` | H:446-473 | host del nick y nicks en ese host | permiso `operserv.clones`; agrupa por IP real y, si no hay, por host |
| `KILLCLONES <nick> [motivo]` | H:476-514 | `TKL + G * <host>`, 60 min, sin KILL | usa el `GLINE ADD` de Ares (`*@<host>`, 1 h) |
| `BLOCK <nick> [motivo]` | H:476-514 | igual, 300 min | `GLINE ADD`, 5 h |
| `CONEXIONES LIST <min>` / `VIEW <host>` | H:832-886 | hosts con al menos `<min>` sesiones, de más a menos | permiso `operserv.conexiones`; `<min>` tiene que ser un entero mayor que 1 |

Protecciones comunes: nunca se banea loopback (`localhost`, `127.0.0.0/8`, `::1`), ni un host donde haya un Root o un IRCop con rol.

---

### Por implementar (T4–T9)

#### JAIL `<nick>` — T4
- **dbots:** H:541-576, E:80-93. Comprobaciones:
  - Falta el nick: `Syntax: JAIL <nick>`.
  - El nick no está conectado.
  - No hay canal cárcel configurado (`DBOTS_JAIL_CHANNEL`).
- **Envía:** `SVSPART <uid> <canal>` por cada canal del usuario, después `SVSMODE <uid> +C`, después `SVSJOIN <uid> <cárcel>`.
- **Falta en Ares:**
  - La acción SVSPART (añadirla a `ProtocolServiceActionsInterface` y a los 3 protocolos).
  - Una forma de listar los canales de un usuario (recorrer `ChannelLookupPort::listAll()` y mirar los miembros, o añadir un índice).
  - La configuración del canal cárcel.
- **Proteger:** Root e IRCops con rol (`ProtectedNetworkTargets`).

#### UMODE `<nick> +/-modos` — T4
- **dbots:** H:579-610. Si los modos contienen `r` o `h`, rechaza con «You cannot set or unset the r and h modes». Esta comprobación va **antes** de mirar si el nick está conectado.
- **Envía:** `SVSMODE <uid> <modos>`. En Ares ya existe `setUserMode`.
- **pulsar:** es un comando de AdminServ, es decir, solo para administradores.

#### APODERA `<#canal> [nick]` — T5
- **dbots:** H:388-443. Comprobaciones:
  - Nombre de canal inválido.
  - El canal no está registrado.
  - El nick no está registrado.
  - Si no se indica nick, el fundador pasa a ser quien ejecuta el comando.
- **Hace:** cambia el fundador y republica en UDB `C::<canal>` (founder) y `C::<canal>::options`.
- **En Ares:** OperServ no puede importar clases de ChanServ. Hay que crear un puerto propio de OperServ que llame a un `Port/In` público de ChanServ, o añadir ese `Port/In` si no existe. Comprobar que el cambio de fundador emite el evento que UnrealUdb ya proyecta.

#### DROPNDB `<nick>` — T5 (Root)
- **dbots:** H:353-385. Comprobaciones: nick inválido; nick no registrado.
- **Hace:** borra la cuenta y envía UDB `DEL` de `N::<nick>::{pass,forbid,suspend,swhois,vhost,automode}`.
- **En Ares:** mirar primero el `DROP` de NickServ (`src/NickServ/Adapter/In/Irc/Command/DropCommand.php`) y su caso de uso. Si ya permite borrar nicks ajenos a un oper, reutilizarlo. **Riesgo alto: borra datos.**

#### SERVERMAP — T6
- **dbots:** H:786-829. Sin servidores conocidos responde «No server map available». Si los hay, dibuja un árbol desde las raíces, con `  +-- ` delante de cada hijo y `  |   ` o espacios para sangrar.
- **dbots** guarda el árbol en `NetworkState::registerServer/unregisterServer` (SID → nombre, padre e hijos), que alimenta con SERVER/SID/SQUIT.
- **En Ares no existe un registro de servidores.** Hay que crearlo en el contexto Irc, alimentado por cada protocolo, y exponerlo con un `Port/In`.

#### ELINE — T7 (origen pulsar `adminserv/elines.c`)
- **`ADD [+expiración] <máscara> <motivo>`:**
  - Si la máscara no lleva `@`, se convierte en `*@<máscara>`.
  - El host tiene que ser una IP válida.
  - Se rechazan duplicados.
  - Cada entrada lleva número, máscara, motivo, quién la puso, fecha y expiración.
- **`DEL <máscara|números>`:** acepta listas como `1,3-5`.
- **`CLEAR`**, **`LIST`**, **`VIEW`** (con detalle) y **`COUNT`**.
- **Envía:** `TKL + E <user> <host> <SID> <expira|0> <ahora> :<motivo>`, y `TKL - E` para quitarla.
- **En dbots** se guarda en la tabla `elines(id, mask UNIQUE en minúsculas, reason, who, created_at, expires_at)`.
- **En Ares hace falta:**
  - Entidad y mapeo XML.
  - Migración, que el CI prueba en sqlite, mysql, mariadb y postgres.
  - Repositorio.
  - Acción de red TKL E.
  - Expiración automática.

#### SPAM — T8 (`op.mrc` :404)
- **`LIST`**:
  - Sin reglas: «No hay reglas».
  - Con reglas: `patrón -> acción en destinos (motivo)`.
- **`ADD <patrón> <acción> [motivo]`**:
  - Acciones de `op.mrc`: `all`, `block`, `dccblock`, `gline`, `gzline`, `kill`, `kline`, `shun`, `tempshun`, `viruschan`, `warn`, `zline`.
  - dbots solo acepta `gline`, `gzline`, `shun`, `kill`, `block`, `warn` y `tempshun`.
  - Valores por defecto en dbots: destinos `cpnN`, ban-time 3600, expira a los 30 días, motivo `Spam`.
- **`DEL <patrón>`**: dbots acepta también `DELETE`.
- **UDB:** `K::F::b64:<base64(patrón)>::{targets,action,ban-time,reason,expires,match-type=simple}`. `match-type` se escribe el último. `DEL` borra las seis claves.

#### SET `<opción> <valor>` — T9 (Root)

| Opción | Clave UDB | Validación |
|---|---|---|
| `SUFIJO` | `S::J` | solo letras |
| `NICKSERV` | `S::N` | `nick!ident@host` |
| `CHANSERV` | `S::C` | `nick!ident@host` |
| `IPSERV` | `S::I` | `nick!ident@host` |
| `PASSPROTECT` | `S::F` | `intentos:segundos` (p. ej. `3:60`) |
| `PREFIJOS` | `S::P` | no vacío |

- **Envía:** `DB * INS <epoch> <seq> S::<clave> :<valor>`.
- **En dbots** también se guarda en la tabla `udb_settings`.
- **En Ares:** antes de nada, buscar si el bloque `S` de UDB ya se genera desde la configuración, con `grep -rn "S::" src/Irc/Adapter/Protocol/UnrealUdb`.

#### SETTIME — T9 (Root)
- **Envía:** `TSCTL SVSTIME <ahora>` (E:63-66).
- **Respuesta:** «Hora de la red resincronizada».
- **En Ares:** acción nueva en los protocolos Unreal. En InspIRCd no se aplica.

---

### No portados (Ares ya lo cubre). Pendiente de que el usuario lo confirme

| dbots / op.mrc | Equivalente en Ares |
|---|---|
| `UNGLINE <user@host>` | `GLINE DEL <máscara\|número>` |
| `VIEWIP <nick>` | NickServ `USERIP <nick>` |
| `RENAME <nick> [nuevo]` | NickServ `RENAME <nick>` (nick aleatorio `Guest-`) |
| `ROOT <contraseña>` | Root por cuenta en la configuración (`ConfiguredRootIdentityRegistry`) |
| `KILL`, `GLINE`, `MOTD`, `RAW`, `GEOIP`, `IRCOP`, `ROLE` | Ya existían en Ares |

---

## Anexo C — Anatomía de un comando de OPeR en Ares (inglés técnico)
Paths relative to C:\IRC\services\ares-sitiochat.

### Contract
- `src/OperServ/Adapter/In/Irc/OperServCommandInterface.php` (extends Help/HelpableCommandInterface): getName, getAliases, getMinArgs, getSyntaxKey, getHelpKey, getOrder, getShortDescKey, getSubCommandHelp, isOperOnly, getRequiredPermission(): ?string, execute(OperServContext). Optional (method_exists): getExampleKey(), getHelpParams().
- Reference command: `src/OperServ/Adapter/In/Irc/Command/KillCommand.php` (name KILL, min args 2, order 10, keys kill.syntax/help/example/short, isOperOnly true, permission OperServPermission::KILL). execute() returns early without sender, builds typed input `KillNetworkUser(actorNickname, serviceNickname: getBotName(), targetNickname, reason, occurredAt)`, `present()` maps outcome enum -> translation key.
- Central checks in `OperServService::dispatchToHandler` (OperServService.php:89-115): permission (error.not_identified / error.root_only / error.permission_denied), oper-only (error.oper_only), min args (error.syntax), then execute. Commands do NOT re-check permissions.
- `OperServContext::isAuthorized`: null -> ircOperator(), IDENTIFIED -> identifiedAccount(), ROOT -> root(), else permission().

### Use case pattern (src/OperServ/Application/UseCase/Kill/)
- Readonly input DTO, Handler, Outcome (string enum), Result (private ctor + named factories).
- Handler deps: NetworkUserLookup (Port/Out, findByNickname(): ?NetworkUser{uid,nickname,ident,hostname,ipBase64,identified,ircOperator}), RootIdentityRegistry, OperatorAccountLookup, OperatorRoleAccess, KillNetworkUser (Port/Out kill(uid, reason): bool), CommandAuditRecorder.
- Protects Root targets and identified +o users with an assigned role (protectedRoot / protectedIrcOperator).
- Audit: build `CommandAuditRecord` (Port/In/Audit/CommandAuditRecord.php: category, service, actor, operation, occurredAt, target, reason, permission, targetHost, targetIp, metadata via SafeAuditMetadata); categories in CommandAuditCategory (OperatorAction, RootAdministration, ResourceOverride, SystemAction). RecordCommandAudit fans out to sinks tagged app.command_audit_sink. OperServ does NOT use IrcopAuditData/CommandOutcome.
- Network action adapter pattern: `src/OperServ/Adapter/Out/Irc/ActiveConnectionKillNetworkUser.php` (ActiveProtocolModuleHolderInterface -> active module + server SID -> getServiceActions()->killUser(sid, uid, reason); return false without module/SID). Copy this for every new action port.

### Registration (config/services.yaml)
- Registry gets `!tagged_iterator operserv.command.new` (~:2260). Tag each command class `tags: ['operserv.command.new']` (KILL at ~:2378). Handlers autowired.
- Port aliases near ~:2366-2373 (NetworkUserLookup -> IrcNetworkUserLookup; KillNetworkUser -> ActiveConnectionKillNetworkUser). Existing OperServ action ports: only KillNetworkUser and GlineNetworkActions.

### Permissions
- `src/OperServ/Application/Security/OperServPermission.php` constants (KILL, GLINE, GEOIP, RAW, MOTD; values `operserv.<x>`).
- `OperServIrcopPermission::getPermissions()` returns the list (tagged permission.provider) -> PermissionRegistry / OperatorPermissionCatalog (used by ROLE PERMS).

### Translations
- Domain `operserv`, files `translations/operserv.<14 locales>.yaml` (ca de el en es eu fr gl it nl pl pt ro tr). KILL block en :191-199. Shared errors :6-12. Params as %name%; %bot% etc. added by context.

### HELP
- Groups hardcoded in `src/OperServ/Adapter/In/Irc/OperServHelpFormatterContextAdapter.php:12-17` HELP_GROUPS: operators_roles (IRCOP, ROLE), network_security (GLINE, GEOIP, KILL), communication (MOTD), low_level (RAW). Labels `help.group.*` in translations (en :34-38). Ungrouped commands fall back at the end.
- Orders in use: 1, 2, 10, 20, 25, 39, 40, 99(HELP).

### Tests to add/update per command
- Command test like tests/OperServ/Adapter/In/Irc/Command/KillCommandTest.php; handler test like tests/OperServ/Application/UseCase/Kill/KillNetworkUserHandlerTest.php (createMock+expects only for interactions, createStub otherwise); adapter test like tests/OperServ/Adapter/Out/Irc/ActiveConnectionKillNetworkUserTest.php.
- `tests/Shared/ServicesCommandHelpAlignmentTest.php:144` assertSame(75, $totalCommands) -> +1 per new command (aliases don't count); all keys in 14 locales; `{A|B}` choices in en syntax must match getSubCommandHelp() names; group label list :165-168 for new help.group keys.
- `tests/OperServ/Application/Security/OperServIrcopPermissionTest.php:37` exact ordered list -> append new permissions. `OperServPermissionTest.php` one test per constant.
- `tests/Shared/Translations/TranslationCatalogTest.php` validates catalogs.

### Network capabilities (src/Irc/Application/Port/In/ProtocolServiceActionsInterface.php, all 3 protocols)
setUserAccount, setUserMode(sid, uid, modes, params) [svsmode], setUserVhost, forceNick(sid, uid, newNick) [svsnick], killUser, introduceService, setChannelModes(sid, chan, modeStr, params, serviceUid, ts), setChannelMemberMode(sid, chan, uid, letter, add, serviceUid, ts), inviteUserToChannel(sid, chan, uid, serviceUid, ts), joinChannelAsService, forceJoin(sid, uid, chan, serviceUid) [svsjoin], setChannelTopic, kickFromChannel(sid, chan, uid, reason, serviceUid), partChannelAsService, addGline(sid, userMask, hostMask, duration, reason), removeGline, addGlineByMask, removeGlineByMask, introducePseudoClient, quitPseudoClient.
- Missing: no force-part (SVSPART), no TKL E (eline), no TSCTL SVSTIME, no spamfilter.

### Network state
- NetworkUserLookupPort (src/Irc/Application/Port/In): findByUid, findByNick, listConnectedUids, isConnectedUid. SenderView fields: uid, nick, ident, hostname, cloakedHost, ipBase64, isIdentified, isOper, serverSid, displayHost, modes. IP decode: NetworkUser::getIpAddress() (src/Irc/Domain/Network/NetworkUser.php:169-184).
- ChannelLookupPort: findByChannelName, listAll -> ChannelView{name, modes, topic, memberCount, members[{uid, roleLetter}], timestamp, modeParams, bans}.
- Missing: users by IP/host (walk listConnectedUids), per-IP counters, server map/links registry, user count on the port (NetworkUserRepositoryInterface::count() exists).

### Existing overlaps
- NickServ RENAME <nick> (random Guest- nick, perm nickserv.rename); NickServ USERIP <nick> (live real IP); NickServ WHOIP <ip> (stored last-connect IP); OperServ GLINE {ADD|DEL|LIST} (DEL by mask or 1-based number); IRCOP/ROLE (Root only); MOTD.

### Quality gates (AGENTS.md §13)
php -l; php bin/console lint:container; php bin/console lint:yaml . --exclude vendor/ --parse-tags; ./vendor/bin/phpstan analyse src/ tests/ --level=max --error-format=raw --no-progress; ./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php; ./scripts/check-coverage.sh 100 --issues (Linux only: Windows lacks pcntl, so on Windows run focused tests with `XDEBUG_MODE=off php vendor/bin/phpunit --no-coverage <paths>`).
