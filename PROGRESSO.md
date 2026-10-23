# Progresso da implementação — ponto de retomada

> Atualizado em 2026-10-23. Este arquivo é o "save game": estado exato,
> próximos passos e decisões aprendidas. Ler junto com `docs/dominio/07-roadmap-mvp.md`.
> **Ao retomar: ler este arquivo primeiro**, depois `git switch symfony` e
> `docker compose up -d`.

---

## Onde estamos

- **Virada de stack (2026-10-23, ADR-012):** saiu o encanamento feito à mão
  (kernel PSR-7/15, php-di, Phinx, PDO). Entrou **Symfony 7.4 LTS + Doctrine
  ORM (mapeamento XML) + Doctrine Migrations + Messenger**, com foco em SOLID e
  padrões de projeto.
- **Branch de trabalho:** `symfony`, criado a partir de `m1-identidade`
  (aproveita os docs atualizados e os VOs do M1).
- **`main`** = `c445eae` (M0 antigo, sem framework). `m1-identidade` = `f68f4f0`
  (migrations Phinx — descartadas, ficam só no histórico).
- **Milestone atual:** **M1** sobre Symfony. M1.1 `adba7db`, M1.2 `091b3c2`.
  M1.3 (autenticação) pronto, não commitado. Próximo: **M1.4 — multiempresa**.
- **Método combinado (2026-10-23):** Claude escreve o código, explicando cada
  padrão, e para no fim de cada sub-passo para o usuário revisar e commitar.

## Como retomar o ambiente

```bash
cd ~/PhpstormProjects/Projeto-estudo-rodolfo
git switch symfony
docker compose up -d
docker compose exec php composer install
docker compose exec php bin/console app:health     # PHP 8.3 / MySQL 8.4 / OK
docker compose exec php composer check             # cs, phpstan, deptrac, container, phpunit
curl localhost:8080/health                          # {"status":"ok"}
```

Banco: `comandas` (dev) e `comandas_test` (testes). Volume novo cria os dois
(`docker/mysql/init.sql`). Aplicar schema:
`bin/console doctrine:migrations:migrate` e `... --env=test`.

---

## Feito

### M0 (refeito sobre Symfony)
| Entrega | Onde |
|---|---|
| Esqueleto `symfony/skeleton` 7.4, `.env` versionado (DB_* + `DATABASE_URL`) | `config/`, `public/`, `bin/` |
| Doctrine: `auto_mapping` off, mapeamento XML do namespace `App\Domain` | `config/packages/doctrine.yaml`, `src/Infrastructure/Persistence/Doctrine/Mapping/` |
| Messenger: `command.bus` (validation + doctrine_transaction) e `event.bus` | `config/packages/messenger.yaml` |
| Portas `CommandBus`/`EventBus` + adapters Messenger | `src/Application/Shared/Bus/`, `src/Infrastructure/Bus/` |
| `Domain` fora do container; `CommandHandler`/`EventHandler` auto-registrados | `config/services.yaml` |
| `GET /health` + teste funcional | `src/Presentation/Http/HealthController.php`, `tests/Presentation/Http/` |
| `app:health` via porta `DatabaseProbe` → `DbalDatabaseProbe` | `src/Presentation/Console/`, `src/Application/Health/`, `src/Infrastructure/Health/` |
| PHPStan 8 (+symfony/doctrine/phpunit), cs-fixer `@Symfony`, Deptrac, Maker | `phpstan.dist.neon`, `.php-cs-fixer.dist.php`, `deptrac.yaml` |
| CI com deptrac + `lint:container`; `composer check` local | `.github/workflows/ci.yml`, `composer.json` |
| ADR-012 + docs 03/05/06/07 atualizados | `docs/dominio/` |

Mantido do M1 antigo: `Money`, `Ulid`, `Identifier` + `CompanyId`/`UserId`/`BranchId`,
`CompanyContext`, `CompanyContextHolder` (ainda em `Domain/Identity`).

### M1.1 — agregado Company (commit `adba7db`)
| Entrega | Onde |
|---|---|
| `Company` (raiz) + `Branch` + `CompanyCapability`; enums `CompanyStatus`, `BranchStatus`, `Capability`; VO `Slug` | `src/Domain/Identity/` |
| `Identifier` movido para `Domain/Shared`; `DomainException`, `Timestamped` | `src/Domain/Shared/` |
| Porta `CompanyRepository` → `DoctrineCompanyRepository` (bind em `services.yaml`) | `src/Infrastructure/Persistence/Doctrine/Repository/` |
| Tipos DBAL: IDs tipados (`IdentifierType` + subclasses), `SlugType`, override `datetime*` → DATETIME(6) UTC | `.../Doctrine/Type/` |
| Mapeamento XML das 3 entidades | `.../Doctrine/Mapping/` |
| `TimestampListener` (onFlush + `ClockInterface`) | `.../Doctrine/Listener/` |
| Migration `companies`, `branches`, `company_capabilities` | `migrations/Version20261023212641.php` |
| Caso de uso `RegisterCompany` + handler; CLI `app:company:register` | `src/Application/Identity/`, `src/Presentation/Console/Identity/` |
| Testes: `CompanyTest`, `SlugTest` (unit), `RegisterCompanyTest` (bus→MySQL) — 31 verdes | `tests/` |
| DAMA test bundle; banco `comandas_test`; CI com `services: mysql` + `schema:validate` | `phpunit.dist.xml`, `docker/mysql/`, `.github/workflows/ci.yml` |

Demo: `bin/console app:company:register "Bar do Zé" bar-do-ze --capability=tables --capability=kitchen`

### M1.2 — agregados User e Role (commit `091b3c2`)
Decisões do usuário: login = **slug + e-mail + senha**; PIN = **escolhe o
usuário, depois digita** (bcrypt, pode repetir); limite de desconto =
**coluna `roles.max_discount_percent`** (`discount_limits` removida).

| Entrega | Onde |
|---|---|
| `Role` (raiz) + `RolePermission`; enum `Permission` (36 códigos do doc 06); `RoleCode` | `src/Domain/Identity/` |
| `DefaultRoles` — matriz do doc 06 §3 gerada da tabela do doc (9 papéis + limites) | `src/Domain/Identity/DefaultRoles.php` |
| `User` (raiz) + `UserRole` (filial opcional); `User::can(Permission, ?BranchId)`, `maxDiscountIn()` | `src/Domain/Identity/` |
| VOs `Email`, `Percentage` (centésimos, sem float) | `src/Domain/Identity/`, `src/Domain/Shared/` |
| Porta `PasswordHasher` → `NativePasswordHasher` (symfony/password-hasher) | `src/Infrastructure/Security/` |
| Tipos `StringValueType` (base de Slug/Email/RoleCode), `PercentageType`, `RoleIdType`, `UserRoleIdType` | `.../Doctrine/Type/` |
| Repositórios `DoctrineUserRepository`, `DoctrineRoleRepository`; `CompanyRepository::getBySlug` | `.../Doctrine/Repository/` |
| Migration users, roles, role_permissions, user_roles | `migrations/Version20261023213802.php` |
| `RegisterCompany` cria os papéis padrão; caso de uso `RegisterUser`; CLI `app:user:register` | `src/Application/Identity/`, `src/Presentation/Console/Identity/` |
| Testes: aceite M1 "WAITER 403 / CASHIER ok em command.close" (no Domain), escopo por filial, limites; integração RegisterUser — 54 verdes | `tests/` |

Demo: `bin/console app:user:register bar-do-ze "Ana" ana@bar.com --role=CASHIER --role=WAITER@<branchId> --pin=1234`

### M1.3 — autenticação (pronto, aguardando commit)
Decisões do usuário: **pareamento de aparelho** (token `d_` por filial, só
lista usuários e troca PIN por token); token de usuário **expira 12h após o
último uso**; **5 falhas / 15 min** por alvo+IP (senha e PIN).

| Entrega | Onde |
|---|---|
| `OpaqueToken` (`u_`/`d_` + 256 bits, SHA-256), `ApiToken` (expiração deslizante), `Device` (pareamento) | `src/Domain/Identity/` |
| Casos de uso `LoginWithPassword` (anti-timing), `LoginWithPin`, `PairDevice` (exige `admin.device.manage`), `Logout`; `TokenPolicy` | `src/Application/Identity/Auth/` |
| Query (CQRS leitura) `DeviceUsersQuery` → `DbalDeviceUsersQuery` (SQL direto) | `src/Infrastructure/Persistence/Dbal/` |
| Security: `OpaqueTokenHandler` (access_token), `AuthenticatedUser`/`AuthenticatedDevice`, `NativePasswordHasher` (custo por env) | `src/Infrastructure/Security/`, `config/packages/security.yaml` |
| Endpoints `/api/auth/login`, `/api/auth/pin`, `/api/auth/logout`, `/api/devices`, `/api/device/users`, `/api/me`; DTOs com `#[MapRequestPayload]` | `src/Presentation/Http/Auth/` |
| Erros JSON padronizados (401/403/422/429), locale pt_BR | `src/Presentation/Http/Error/`, `config/packages/translation.yaml` |
| Rate limiter `login` | `config/packages/rate_limiter.yaml` |
| Migrations `api_tokens`, `devices` + **migration de dados** (`admin.device.manage` p/ empresas existentes) | `migrations/` |
| Testes: `ApiTokenTest` (expiração), `AuthFlowTest` (HTTP ponta a ponta, MockClock, 429) — 69 verdes | `tests/` |

---

## Próximos passos — M1 sobre Symfony

1. ~~Agregado Company~~ (M1.1). ~~User + Role~~ (M1.2).
2. ~~Autenticação~~ (M1.3).
3. **M1.4 — Multiempresa:** filtro SQL do Doctrine (`CompanyFilter`) ligado com
   o `CompanyContext`; `CompanyContextHolder` para `Application` com
   `ResetInterface`. Aceite: usuário da empresa A não vê dado da B.
4. **M1.5 — Autorização:** Voter que delega para `User::can()`;
   `CapabilityChecker` (`hotel` = false na demo). Aceite HTTP: WAITER 403 /
   CASHIER 200 em `command.close`.
5. **M1.6 — Seed demo:** `app:seed-demo` (empresa + filial + 1 usuário por papel).
6. **`counters`** (numeração diária de comanda) — fica para o M3, onde é usado.

---

## Decisões e aprendizados (não repetir os erros)

- **Nomes:** `Company` (não `Tenant`), `Branch` (não `Unit`). `company_id`,
  `branch_id`. "multiempresa", "filial" na prosa. Preservado: `unit_price` e
  `stocks.unit`/`recipe_items.unit` (medida un/kg/L).
- **Encoding:** só UTF-8. O PhpStorm do usuário salva em ISO-8859-1 por padrão —
  conferir Settings → Editor → File Encodings tudo UTF-8. `.editorconfig` força,
  CI verifica. Converter: `iconv -f ISO-8859-1 -t UTF-8 arquivo -o arquivo`.
- **Recipes do Flex** escrevem coisas por conta própria: o do Doctrine pôs um
  `DATABASE_URL` de Postgres no `.env` e criou `src/Entity`/`src/Repository`.
  Revisar `git status` depois de todo `composer require`. Recipes de Docker
  desligados (`extra.symfony.docker: false`).
- **Doctrine + PHP 8.3:** entidade mapeada não pode ser `final` (proxy), e o
  `$id` não pode ser `readonly` ("Attempting to change readonly property ...::$id"
  ao inicializar um proxy lazy-ghost).
- **Enum em campo de PK** não é aceito no XML do Doctrine → guardar string e
  converter na entidade (`RolePermission`).
- **`findBy` por associação** com IDs tipados: passar o objeto (`CompanyId`),
  não `->toString()` — o tipo DBAL rejeita string de propósito.
- **Domain fora do container** → interface de porta precisa de alias explícito
  em `config/services.yaml`, senão "Cannot autowire ... has been excluded".
- **DATETIME(6):** criar só um tipo novo deixava o diff eterno (introspecção lê
  DATETIME). Solução: sobrescrever `datetime`/`datetime_immutable` do DBAL.
- **Recipes contrib** (ex.: DAMA) não rodam (`allow-contrib: false`): registrar
  bundle em `config/bundles.php` e config à mão.
- **DBAL 4:** `ConversionException::conversionFailed` não existe mais →
  `Types\Exception\ValueNotConvertible::new()`.
- **Rate limiter em teste:** `ArrayAdapter` é zerado pelo `services_resetter`
  entre requests do `KernelBrowser` → usar o cache padrão e dar
  `cache.rate_limiter->clear()` no `setUp`.
- **bcrypt em teste:** custo 4 (`when@test` em `services.yaml`) — sem isso a
  suíte levava 24 s em vez de 1 s.
- **XML do Doctrine:** `options` é elemento filho (`<options><option name="fixed">true</option></options>`), não atributo.
- **PHPStan** lê o container compilado (`var/cache/dev/...xml`): rodar
  `bin/console cache:warmup` antes se o cache estiver limpo. Cache velho depois
  de `composer require` → `vendor/bin/phpstan clear-result-cache`.
- **`user_roles.branch_id`** = `CHAR(26) NOT NULL DEFAULT ''` — `''` significa
  "papel na empresa toda". Sentinela em vez de NULL (PK não aceita NULL). Sem FK.
- **Método de trabalho:** explicar cada conceito/padrão ao aplicar; usar as
  ferramentas (Maker, `make:migration`) e revisar o gerado; sempre `composer check`
  antes de commitar. Identidade git local = pessoal (`rodolfo.nakamichi11@gmail.com`).
