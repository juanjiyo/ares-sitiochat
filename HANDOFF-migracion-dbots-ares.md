# Handoff — Migración `dbots-services` → `ares` (VPS 5.250.189.222)

Documento de traspaso. Todo lo que sigue está verificado contra el repositorio y el VPS, no es deducción.

---

## 1. Entorno

| Elemento | Valor |
|---|---|
| Repo local | `/tmp/ares-sitiochat` → `github.com/juanjiyo/ares-sitiochat` (rama `main`) |
| HEAD al escribir esto | `c7dc489` (pusheado); actualizado a `a1e6644` el 2026-10-29, desplegado en el VPS |
| VPS | `/home/irc/ares-sitiochat`, usuario `irc`, SSH `-i ~/.ssh/irc_vps_key` |
| IRCd | UnrealIRCd 6.2.7 + módulos `third/udb` 4.0.0-rc1 y `third/sacmds` 2.2.3 |
| Servicios vivos | `NiCK 003A00001`, `CHaN 003B00002`, `MeMo 003C00003`, `CReG 003D00004`, `GlobalServ 003Z00005`, `HeLP 003Z00006`, `OPeR 003E00007` |
| Stack | PHP 8.5, Symfony 7.4, Doctrine ORM 3.6, PHPUnit 13.4.5, PHPStan max, PHP-CS-Fixer |
| Gate de cobertura | **100% de líneas obligatorio** (`./scripts/check-coverage.sh 100 --issues`) |

---

## 2. Estado actual de CI (leer primero)

CI fallaba en **todas** las ejecuciones desde antes de esta sesión. Causas encontradas y ya corregidas:

1. **`composer audit`** rompía el job antes de compilar: advisory `GHSA-9vw2-f4vp-x7gf` de PHPUnit (`>=13.4.0,<13.4.4`). Subido a **13.4.5** (commit `3a4ee52`). `composer audit` ahora limpio.
2. **Test dependiente del reloj**: `ManageChannelAkickHandlerTest::checkReturnsTheEntriesMatchingTheConnectedUser` creaba el akick caducado con `new DateTimeImmutable('-1 day')` mientras el `now` del test es fijo (`2026-10-20`). Corregido a `$this->now->modify('-1 day')`.
3. **Conteo de comandos** en `tests/Shared/ServicesCommandHelpAlignmentTest.php`: esperaba 73, ahora **75**. Ese test exige además que **toda clave de traducción exista en las 14 locales**.
4. **Cobertura**: estaba en 98,26% (HeLPServ ya la había roto; GlobalServ y los comandos nuevos añadían más). Restaurada a **100%** en `c7dc489`.

**Verificado (2026-10-29)**: en el run `40616471727` (`a1e6644`) pasan `PHP 8.5 — Lint, CS & Tests` y las 4 migraciones (mysql, mariadb, sqlite, postgres). El job `Deploy Ares IRC Services` queda siempre en cola: espera un runner Docker `ares-deploy` que no existe (no se usa Docker). El VPS se despliega a mano y ya corre `a1e6644`.

Para revisar ejecuciones futuras:

```bash
cd /tmp/ares-sitiochat && gh run list --limit 1 --json databaseId,status,conclusion
gh run view <id> --json jobs | python3 -c "import sys,json;d=json.load(sys.stdin);[print(j['name'],j['conclusion']) for j in d['jobs']]"
```

Si sigue rojo, mirar el paso concreto, no asumir.

---

## 3. Qué está hecho

### Servicios completos

| Servicio | Comandos |
|---|---|
| **NiCK** | Servicio completo del repo original |
| **CHaN** | Ver tabla de paridad abajo |
| **MeMo** | Servicio completo |
| **CReG** | `REGISTRA`, `APROBAR`, `DENEGAR`, `PENDIENTES`, `DENEGADOS`, `ESTADO`, `CANCEL`, `DROP` |
| **HeLP** | Árbol de ayuda de 6 tópicos (UMODES, CMODES, BOTS, NICK, CANAL, CREDITOS) + `HELP`/`AYUDA` |
| **GlobalServ** | `GLOBAL`, `NICK`, `CHAN`, `JOIN`, `PRE-OPER`, `OPER`, `ADMIN`, `HELP` (`CO-ADMIN` eliminado en `c635f62`: ares no tiene ese rango) |
| **OPeR** | Servicio completo (`KILL`, `GLINE`, `RAW`, `MOTD`, `ROLE`, `IRCOP`, `GEOIP`); `GLOBAL` movido a GlobalServ en `cbfe2d3` |

### Commits relevantes

```text
c7dc489 test: restore full line coverage for the new service code
3a4ee52 test: update PHPUnit for the security advisory and the command help count
5725d1d feat(chanserv): add LISTSUSPEND and LISTFORBID oper listings
d2b8780 feat(chanserv): add SET TOPIC and SET NOEXPIRE options
fcbbfd6 feat(globalserv): add GlobalServ network announcement service
```

### Paridad ChanServ con `ch.mrc` (fuente autoritativa: `/home/juanjo/irc/sitiochat/sistema/ch.mrc`)

| ch.mrc | Estado en ares |
|---|---|
| `HELP/AYUDA`, `INFO`, `LIST`, `DROP` | ✅ |
| `OP/DEOP/HALFOP/DEHALFOP/VOICE/DEVOICE` | ✅ |
| `INVITE`, `UNBAN` | ✅ |
| `ACCESS`, `DELACCESS` | ✅ (ares añade `CLEARACCESS`, `CLEARUSERS`) |
| `AKICK` | ✅ |
| `LEVELS` | ✅ |
| `SUSPEND`, `UNSUSPEND`, `LISTSUSPEND` | ✅ |
| `FORBID`, `UNFORBID`, `LISTFORBID` | ✅ |
| `NOEXPIRE` | ✅ (comando suelto, como en `ch.mrc`) |
| `SET FOUNDER/SUCCESSOR/DESC/URL/EMAIL/ENTRYMSG/MLOCK/TOPICLOCK/PRIVATE/SECURE` | ✅ |
| `SET TOPIC`, `SET NOEXPIRE` | ✅ (nuevos) |
| `SET KEEPTOPIC/DEFTOPIC` | ✅ (`578fa86`; en ares la opción se llama `DEFAULTTOPIC`) |
| `SET RESTRICTED/SECUREOPS/SECUREHALFOPS/DEBUG/AUTOLIMIT` | ❌ **pendiente** |
| `CLEAR MODES\|BANS\|OPS\|HALFOPS\|VOICES\|USERS\|TOPIC` | ❌ parcial (`CLEARUSERS`, `CLEARACCESS`; faltan MODES/BANS/OPS/HALFOPS/VOICES/TOPIC) |
| `KICK` | ✅ (`494668a`) |
| `BFOUNDER`, `ESPECIAL` | ❌ pendiente / decisión |
| `IDENTIFY`, `GETPASS`, `SET PASSWORD` | ❌ **no aplica**: ares no usa contraseña de canal, el fundador es la cuenta de NiCK |

> Nota importante: `pulsar/modules/chanserv` **no** tiene `SET NOEXPIRE`; `ch.mrc` **sí**. La fuente que manda es `ch.mrc`.

---

## 4. Lo que falta, por orden recomendado

### 4.1. ChanServ — flags de `SET` con estado nuevo (bloque grande)

Requiere, por cada flag: columna + migración + proyección + handler + reactor real. **No publicar flags que no hagan nada.**

- ~~`KEEPTOPIC`, `DEFTOPIC`~~ ✅ hechos (`578fa86`, migración `Version20261027000001`).
- Pendientes: `RESTRICTED`, `SECUREOPS`, `SECUREHALFOPS`, `DEBUG`, `AUTOLIMIT`.
- Columnas actuales en `registered_channels`: `topic_lock`, `mlock_active`, `mlock`, `mlock_params`, `secure`, `topic`, `no_expire`, `private`, `ircop_only`, `suspended_*`, `forbidden_reason`, `status`.
- `src/ChanServ/Application/Port/In/ChannelProjection.php` expone solo: `topicLock`, `secure`, `ircopOnly`, `private`, etc. Hay que extenderlo.
- Reactores existentes donde enganchan: `src/ChanServ/Domain/Policy/RankReconciliationPolicy.php` (ya recibe `bool $secure`), `ChanServTopicSyncSubscriber`, `ChanServTopicApplySubscriber`, `IrcChannelTopicActions`.
- `AUTOLIMIT` necesita calcular límite y aplicar `+l`; `SECUREOPS/SECUREHALFOPS` enganchan en la reconciliación de rangos al `SJOIN`; `DEBUG` es logging por canal.

### 4.2. ChanServ — comandos sin migración (más baratos)

- `CLEAR <#canal> MODES|BANS|OPS|HALFOPS|VOICES|TOPIC`: reutiliza `ChannelServiceActionsPort` (`setChannelModes`, `setChannelMemberMode`) y `ChannelView::members`/`bans`.
- ~~`KICK <#canal> <nick> [motivo]`~~ ✅ hecho (`494668a`).
- `BFOUNDER`: decidir si se implementa (en `ch.mrc` es un flujo de "fundador por bot").
- `ESPECIAL`: en dbots son bots de entrada; probablemente descartar.

### 4.3. Servicios completos que faltan

| Servicio dbots | Fichero fuente | Contenido |
|---|---|---|
| **CeNTeRServ** | `ce.mrc` (49 KB) | Escalera de staff: `PREOPER`, `OPER`, `ADMIN`, `ROOT`, `REMOPER`, `MODULO`, `RAW/NORAW`, `LISTSUSPEND`; es el backend de rangos que usan GlobalServ/NoServ |
| **CLoNeS** | `caos.mrc`/módulos | `ClonesServ`: gestión de clones/excepciones |
| **NoServ** | `no.mrc` | `ALTA`, `BAJA`, `EMAIL`, `MOTD`, `NICK`, `REPRESENTANTES`, `NOTICIAS`, `SERVIDOR` |
| **Sumidero** | módulo en `modulos/` | Canal sumidero de mensajes |
| **DeBuG relay** | `de.mrc` (13 KB) | **Nada escribe hoy en `#Debug`**; es lo que el usuario preguntó al principio |
| **Ping** | `pr.mrc` | Servicio de ping/respuesta |
| **Sistema de módulos** | `CeNTeR MODULO LIST/LOAD/UNLOAD` | Pseudocliente por módulo |

Tareas ya numeradas en **`odd/tasks/migrate-missing-services.md`** (T1–T6 hechas, T7+ pendientes). Actualizar ese documento al cerrar cada tarea, con commit como evidencia.

### 4.4. Detalles sueltos

- **Ayuda de HeLP, tópico NICK**: apunta a NiCK; en el resto de servicios el flujo ya está corregido (CReG sin contraseña).
- **`GlobalServ JOIN`** devuelve *no soportado*: `ProtocolServiceActionsInterface` no tiene `SVSJOIN`. Para habilitarlo hay que añadir la operación a `UnrealStandalone` y `UnrealUdb` (prohibido emitir líneas crudas desde un servicio).
- **Root/identificación**: `OPERSERV_ROOT_USERS=JuanJo_Jaen` en `.env.local`; requiere `+r` (SASL o `PASS cuenta:contraseña` de UDB). El usuario pidió **no tocar más UDB**.

---

## 5. Receta para añadir un comando (seguir al pie de la letra)

1. **Contrato**: `src/<Servicio>/Adapter/In/Irc/<Servicio>CommandInterface.php` exige `getName`, `getAliases`, `getMinArgs`, `getSyntaxKey`, `getHelpKey`, `getOrder`, `getShortDescKey`, `getSubCommandHelp`, `isOperOnly`, `getRequiredPermission`, `execute`.
2. **Permiso**: añadir constante en `src/<Servicio>/Application/Security/<Servicio>Permission.php` **y** en `allIrcop()`. Si es servicio nuevo, crear `<Servicio>IrcopPermission implements PermissionProviderInterface` y etiquetarlo `permission.provider`.
3. **Caso de uso** en `src/<Servicio>/Application/UseCase/<Caso>/` con puertos `Port/Out` propios. Nunca importar el dominio de otro contexto.
4. **Adaptador de red** en `src/<Servicio>/Adapter/Out/Irc/` usando los puertos de `App\Irc\Application\Port\In`. Duplicar el patrón dentro del servicio está permitido y es lo esperado (`.agents/protocol.md`).
5. **Wiring** en `config/services.yaml`: etiqueta `<servicio>.command` (p.ej. `chanserv.command`), alias de los puertos nuevos, parámetros escalares con `%...%`.
6. **Traducciones**: `translations/<servicio>.{ca,de,el,en,es,eu,fr,gl,it,nl,pl,pt,ro,tr}.yaml`. ES y EN completos, el resto puede ser copia del inglés **pero todas las claves deben existir**.
7. **Tests** en `tests/<Servicio>/...` con `#[CoversClass(...)]` por clase (si falta, PHPUnit marca *risky*).
8. **Actualizar** `tests/Shared/ServicesCommandHelpAlignmentTest.php` con el nuevo total de comandos.
9. **Gates** (ver §6) y **commit** convencional.

### Reglas de estilo del repo (`AGENTS.md`)

- `declare(strict_types=1)`, `final readonly` donde aplique, Yoda conditions.
- Prohibido `@phpstan-ignore`, baselines o `@codeCoverageIgnore`.
- Sin atribución de IA en commits.
- Documentación y strings de UI en inglés; traducciones al resto de locales.

---

## 6. Verificación obligatoria antes de decir "hecho"

```bash
cd /tmp/ares-sitiochat
php -l <cada fichero modificado>
php bin/console lint:container
php bin/console lint:yaml . --exclude vendor/ --parse-tags
./vendor/bin/phpstan analyse src/ tests/ --level=max --error-format=raw --no-progress   # 0 errores
./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php
./scripts/check-coverage.sh 100 --issues                                                # 100% y 0 fallos
```

---

## 7. Despliegue al VPS

El VPS **no tiene credenciales de GitHub** (`could not read Username`), así que se despliega por `rsync` desde local:

```bash
rsync -az -e "ssh -i ~/.ssh/irc_vps_key" \
  --exclude='.env.local' --exclude='.env' --exclude='var/data/*' \
  --exclude='var/cache/*' --exclude='var/log/*' --exclude='.git' \
  --exclude='vendor' --exclude='node_modules' --exclude='public/bundles' \
  /tmp/ares-sitiochat/ irc@5.250.189.222:/home/irc/ares-sitiochat/
```

Luego, en el VPS:

```bash
cd /home/irc/ares-sitiochat
php bin/console cache:clear --env=prod
php bin/console lint:container
pkill -f "irc:connect"; sleep 3
nohup setsid php bin/console irc:connect -vv >> var/ares.log 2>&1 < /dev/null &
```

Verificar en **otra** conexión SSH (la primera se queda colgada por el proceso en background):

```bash
pgrep -af "irc:connect"
grep "introduced to network" /home/irc/ares-sitiochat/var/log/irc-$(date +%F).log | tail -n 8
```

> **El VPS está desplegado con `5725d1d`, no con `c7dc489`.** Hay que redesplegar: `c7dc489` incluye el cambio de UID de pseudocliente al bloque `Y` (evita colisión con `Z` de GlobalServ/HeLP) y el refactor de `present()` de los comandos. Solo hay cambios de tests en `3a4ee52`, pero el lock de Composer cambió (PHPUnit dev).

---

## 8. Trampas conocidas

| Trampa | Detalle |
|---|---|
| `.env.local` con espacios | `HELPSERV_REALNAME=Help Service` rompe Symfony Dotenv. Usar comillas: `"Help Service"` |
| UIDs de servicio | `ServiceUidGenerator` comparte contador y usa `Z` por defecto → HeLP y GlobalServ comparten bloque. Los pseudoclientes usan `Y` desde `c7dc489` |
| Migraciones en VPS | 4 filas "migrated, not available" de un repo antiguo (`davidlig`). Son warnings, `migrate` funciona |
| Reinicio de IRCd tras tocar UDB | `./unrealircd stop; rm -rf data/udb data/.udb_state*; ./unrealircd start` (usuario `irc`, no root) |
| `lint:yaml` | Necesita `--parse-tags` por los `!tagged_iterator` |
| Tests con `final readonly` | No se pueden mockear; usar el handler real con puertos stub |
| Cobertura | Cualquier rama nueva sin test rompe CI. Los `match` no exhaustivos fallan PHPStan; preferir `if` con guardas |
| `composer audit` | Cualquier advisory nuevo rompe CI antes de compilar |

---

## 9. Decisiones abiertas (requieren al usuario)

1. **Contraseña de canal**: `ch.mrc` tiene `IDENTIFY`/`SET PASSWORD`/`GETPASS`. ares usa cuenta NiCK como fundador. ¿Se mantiene el modelo de ares o se añade contraseña de canal por compatibilidad?
2. **`GlobalServ JOIN`**: ¿se añade `SVSJOIN` a los dos adaptadores de Unreal?
3. **`ESPECIAL` / `BFOUNDER`**: ¿se implementan o se descartan formalmente?
4. **`CLoNeS`**: alcance real (en dbots son excepciones de clones; ares no tiene módulo equivalente).
5. **Estilo de nicks**: `GlobalServ` no existía en dbots; ¿mantener nombre largo o `GLoBaL`?

---

## 10. Archivos de referencia

```text
# Fuente autoritativa del comportamiento dbots
/home/juanjo/irc/sitiochat/sistema/ch.mrc        (ChanServ, 2171 líneas)
/home/juanjo/irc/sitiochat/sistema/ni.mrc        (NickServ)
/home/juanjo/irc/sitiochat/sistema/ce.mrc        (CeNTeRServ)
/home/juanjo/irc/sitiochat/sistema/no.mrc        (NoServ)
/home/juanjo/irc/sitiochat/sistema/de.mrc        (DeBuG)
/opt/dbots-services/docs/commands.md             (inventario y estados)

# Implementación de referencia en ares
/tmp/ares-sitiochat/src/HelpServ/                 (servicio mínimo completo)
/tmp/ares-sitiochat/src/GlobalServ/               (servicio con permisos y varios puertos)
/tmp/ares-sitiochat/src/CRegServ/                 (autorización IRCop + aprobación)
/tmp/ares-sitiochat/config/services.yaml          (wiring: parámetros, bots, listeners, comandos)
/tmp/ares-sitiochat/odd/tasks/migrate-missing-services.md   (plan T1–T17)

# Puertos del núcleo IRC
src/Irc/Application/Port/In/SendNoticePort.php
src/Irc/Application/Port/In/NetworkUserLookupPort.php
src/Irc/Application/Port/In/ChannelLookupPort.php
src/Irc/Application/Port/In/ChannelServiceActionsPort.php
src/Irc/Application/Port/In/ProtocolServiceActionsInterface.php
```

---

**Resumen ejecutivo**: los siete servicios están en producción y CI está a punto de quedar verde (verificar el último run). Lo que queda es, por orden de valor: **flags de `SET` de ChanServ con migración**, **comandos `CLEAR`/`KICK`**, y después los servicios completos **CeNTeRServ → DeBuG → NoServ → CLoNeS → Sumidero → Ping**.
