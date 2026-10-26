# Progresso da implementação — ponto de retomada

> Atualizado em 2026-10-26 (M3.3 implementado na branch `feat/m3.3-outbox-auditoria`, aguardando commit/PR). Este arquivo é o "save game": estado exato,
> próximos passos e decisões aprendidas. Ler junto com `docs/dominio/07-roadmap-mvp.md`.
> **Ao retomar: ler este arquivo primeiro**, depois `git switch main && git pull` e
> `docker compose up -d`.

---

## Onde estamos

- **Virada de stack (2026-10-23, ADR-012):** saiu o encanamento feito à mão
  (kernel PSR-7/15, php-di, Phinx, PDO). Entrou **Symfony 7.4 LTS + Doctrine
  ORM (mapeamento XML) + Doctrine Migrations + Messenger**, com foco em SOLID e
  padrões de projeto.
- **Fluxo de branches (2026-10-26):** cada sub-passo numa branch própria criada
  da **`main`** (`feat/m2.4-busca`, `fix/...`, `docs/...`); o usuário commita,
  faz push e junta por **PR no GitHub** (PR #5 = M2.3). A `symfony` (onde
  M1→M2.2 foram feitos) está toda na `main` e não é mais usada.
- Histórico antigo: `m1-identidade` = `f68f4f0` (migrations Phinx descartadas).
- **Milestone atual:** **M2 — Catálogo.** M2.1 `2c35dd2`. M2.2 `c8a19e3`.
  M2.3 `1db325a` (PR #5). M2.4 `208b2db` (PR #6). M2.5 `1a6269c` (PR #7) — **M2 concluído**.
- **M3 — Comanda + Pedido** em andamento: M3.1 `fa0f947` (PR #8). M3.2
  `057f001` (PR #9). M3.3 (outbox + auditoria) implementado em
  `feat/m3.3-outbox-auditoria` (não commitado). Próximo: **M3.4 — descontos**.
- **M1 concluído** sobre Symfony. M1.1 `adba7db`, M1.2 `091b3c2`.
  M1.3 `7958884`. M1.4 `1e6a37a`. M1.5 `551da3e`. M1.6 `db5789c`.
- **Método combinado (2026-10-23):** Claude escreve o código, explicando cada
  padrão, e para no fim de cada sub-passo para o usuário revisar e commitar.

## Como retomar o ambiente

```bash
cd ~/PhpstormProjects/Projeto-estudo-rodolfo
git switch main && git pull
docker compose up -d
docker compose exec php composer install
docker compose exec php bin/console app:health     # PHP 8.3 / MySQL 8.4 / OK
docker compose exec php composer check             # cs, phpstan, deptrac, container, phpunit
curl localhost:8080/health                          # {"status":"ok"}
docker compose exec php bin/console app:seed-demo   # empresa demo (senha demo1234, PIN 1234)
docker compose exec php bin/console app:seed-demo-catalog --extra=1000   # cardápio, mesas + 1k produtos
docker compose exec php bin/console app:outbox:publish  # despacha eventos (auditoria); em produção: cron a cada minuto
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

### M1.3 — autenticação (commit `7958884`)
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

### M1.4 — multiempresa (commit `1e6a37a`)
Decisão do usuário: filtro **ligado na autenticação** (token de usuário ou de
aparelho); rotas públicas e CLI sem filtro, com `companyId` explícito nos repositórios.

| Entrega | Onde |
|---|---|
| `CompanyFilter` (SQLFilter): `company_id = ?` em toda entidade com associação `company`; `id = ?` na `Company` | `src/Infrastructure/Persistence/Doctrine/Filter/`, `config/packages/doctrine.yaml` |
| `CompanyContext::forUser()/forDevice()` (aparelho: sem usuário, com filial) | `src/Domain/Identity/CompanyContext.php` |
| Porta `CompanyContextProvider` (só leitura) | `src/Application/Shared/Context/` |
| `CompanyContextHolder` (Infrastructure, `ResetInterface`): guarda contexto + liga/desliga o filtro | `src/Infrastructure/Security/` |
| `EnterCompanyContextOnLogin` (listener de `LoginSuccessEvent`) | `src/Infrastructure/Security/` |
| Testes: aceite "A não vê B mesmo forçando id" (find, DQL, JOIN, slug), cobertura do filtro, contexto via HTTP — 77 verdes | `tests/Integration/MultiCompany/`, `AuthFlowTest` |
| ADR-004 revisada | `docs/dominio/00-decisoes-arquiteturais.md` |

### M1.5 — autorização (commit `551da3e`)
Decisão do usuário: aceite HTTP por um endpoint **"posso?"** (o `command.close`
real só nasce no M3). Regra de estilo nova: **nomes de variável por extenso**
(única exceção: `$db`) — renomeado no projeto todo.

| Entrega | Onde |
|---|---|
| `PermissionChecker` (`can`/`assert`, ator explícito → `User::can`) | `src/Application/Identity/Authorization/` |
| `CapabilityChecker::has(CompanyId, Capability)` | `src/Application/Identity/Authorization/` |
| `PermissionVoter` (atributo = código do doc 06, sujeito = filial) | `src/Infrastructure/Security/` |
| `User::permissionsIn(?BranchId)` | `src/Domain/Identity/User.php` |
| `GET /api/me/permissions[?branchId=]` (lista) e `/api/me/permissions/{code}` (204/403/404) | `src/Presentation/Http/Identity/` |
| `PairDeviceHandler` usa o `PermissionChecker` | `src/Application/Identity/Auth/` |
| Testes: aceite WAITER 403 / CASHIER 204 em `command.close`, `hotel` = false — 83 verdes | `tests/Presentation/Http/Identity/`, `tests/Integration/Identity/` |

### M1.6 — seed demo (commit `db5789c`)
| Entrega | Onde |
|---|---|
| `app:seed-demo`: empresa `demo` (tables+kitchen, sem hotel), filial Matriz, 1 usuário por papel (`dono@`, `admin@`, `auditor@` na empresa toda; `gerente@`, `caixa@`, `garcom@`, `cozinha@`, `bar@`, `estoque@` na Matriz), senha `demo1234`, PIN `1234` para quem opera | `src/Presentation/Console/Demo/SeedDemoCommand.php` |
| Despacha os casos de uso (não SQL) numa transação só; idempotente; recusa rodar em `prod` | idem |
| Testes: 1 usuário por papel, `hotel` = false na demo, reexecução não duplica — 85 verdes | `tests/Presentation/Console/` |

### M2.1 — estações e categorias (commit `2c35dd2`)
Decisões do usuário (2026-10-26): cardápio **por filial**; **todo produto tem
≥1 variação** (sem `base_price_cents`); **só API** (telas admin adiadas);
**referência entre agregados por ID** (doc 02), com FK no banco por convenção.

| Entrega | Onde |
|---|---|
| Agregados `Station` e `Category` (arquivar em vez de apagar), IDs, repositórios, `NotFound` | `src/Domain/Catalog/` |
| `Domain\Shared\NotFound` → HTTP 404 (os `*NotFound` de Identity passam a herdar dele) | `src/Domain/Shared/`, `ApiExceptionListener` |
| `BranchAuthorizer`: filial da empresa do ator (404) → ativa → permissão (403); devolve a empresa | `src/Application/Identity/Authorization/` |
| Casos de uso `CreateStation` (exige `kitchen`), `UpdateStation`, `CreateCategory`, `UpdateCategory`, `ArchiveCategory` | `src/Application/Catalog/` |
| Read model `CatalogQuery` → `DbalCatalogQuery` | `src/Application/Catalog/`, `src/Infrastructure/Persistence/Dbal/` |
| `IdentifierForeignKeys` (listener `postGenerateSchemaTable`): FK para todo campo de ID tipado | `src/Infrastructure/Persistence/Doctrine/Schema/` |
| `CompanyFilter` reconhece o campo `companyId` | `.../Doctrine/Filter/` |
| Migration `stations`, `categories` | `migrations/Version20261026201102.php` |
| API: `GET/POST /api/branches/{id}/stations`, `PATCH /api/stations/{id}`, `GET/POST /api/branches/{id}/categories`, `PATCH/DELETE /api/categories/{id}` | `src/Presentation/Http/Catalog/` |
| Testes: unit de Station/Category, HTTP (permissão, capability, nome único, isolamento 404, arquivar) — 97 verdes; trait `JsonApiClient` | `tests/` |

### M2.2 — agregado Product (commit `c8a19e3`)
Decisão do usuário: grupos de modificadores **por produto** (como o doc 03).

| Entrega | Onde |
|---|---|
| `Product` (raiz) + `ProductVariant` (preço, ≥1 ativa), `ModifierGroup` (min/max alcançável) + `Modifier` (delta pode ser ≤ 0); `NewVariant`/`NewModifier`; `ProductKind`; `CatalogName` | `src/Domain/Catalog/` |
| Tipo `money` (Money ⇄ BIGINT centavos, só BRL) + tipos de ID | `.../Doctrine/Type/` |
| Mapeamento XML com cascade persist + orphan-removal e `order-by sortOrder` | `.../Doctrine/Mapping/Catalog.*.orm.xml` |
| `IdentifierForeignKeys` passou para `postGenerateSchema` (corrige o charset perdido) | `.../Doctrine/Schema/` |
| Migration products, product_variants, modifier_groups, modifiers | `migrations/Version20261026202617.php` |
| Testes: aceite Chopp 300/500ml e X-Burger Ponto(1)/Adicionais(0–5) no Domain, invariantes, ida e volta no MySQL — 111 verdes | `tests/Domain/Catalog/`, `tests/Integration/Catalog/` |

### M2.3 — API de produto (commit `1db325a`, PR #5)
Regra tomada (doc 06): operação que **define valor** exige `catalog.price.manage`
além de `catalog.product.manage`.

| Entrega | Onde |
|---|---|
| Casos de uso `CreateProduct` (completo, com grupos), `UpdateProduct` (PATCH; `null` limpa categoria/estação), `AddProductVariant`, `UpdateProductVariant`, `AddModifierGroup`, `ChangeModifierGroupLimits`, `AddModifier`, `ChangeModifierStatus`; inputs `VariantInput`/`ModifierGroupInput`/`ModifierInput` | `src/Application/Catalog/Product/` |
| `CatalogReferences`: categoria/estação da mesma filial (senão 404), não arquivada/ativa | `src/Application/Catalog/` |
| `BranchAuthorizer::authorize(actor, branch, ...permissões)` (variádico) | `src/Application/Identity/Authorization/` |
| Read model `products()`/`product()` em lote (4 consultas, sem N+1) + views | `src/Application/Catalog/`, `DbalCatalogQuery` |
| API: `GET/POST /api/branches/{id}/products[?categoryId=]`, `GET/PATCH /api/products/{id}`, `POST .../variants`, `PATCH .../variants/{id}`, `POST .../modifier-groups`, `PATCH .../modifier-groups/{id}`, `POST .../modifiers`, `PATCH .../modifiers/{id}` — respostas devolvem o produto atualizado | `src/Presentation/Http/Catalog/ProductController.php` |
| Testes: aceites Chopp/X-Burger pela API, 422 com mensagem do Domain, preço × papel "CARDAPISTA", PATCH ausente × null, isolamento — 119 verdes | `tests/Presentation/Http/Catalog/ProductApiTest.php` |

### M2.4 — busca (commit `208b2db`, PR #6)
| Entrega | Onde |
|---|---|
| Índice `FULLTEXT ft_products_name` + migration | `Catalog.Product.orm.xml`, `migrations/Version20261026203908.php` |
| `FulltextQuery`: texto do usuário → modo booleano (`cho` → `+cho*`; palavras < 3 letras e operadores saem) | `src/Infrastructure/Persistence/Dbal/` |
| `CatalogQuery::search()` (só ativos, por relevância, limite 20) | `DbalCatalogQuery` |
| `GET /api/branches/{id}/catalog/search?q=` | `ProductController` |
| `app:seed-demo-catalog [--extra=N]`: cardápio real (Chopp, X-Burger, Caipirinha...) + N sintéticos, pelos casos de uso | `src/Presentation/Console/Demo/` |
| Testes: unit do `FulltextQuery`; aceite "Chopp em < 50 ms com 1k" (mediana de 5) + `EXPLAIN` usa o índice — commitando de verdade (`#[SkipDatabaseRollback]`) — 128 verdes | `tests/Infrastructure/`, `tests/Presentation/Http/Catalog/CatalogSearchTest.php` |

### M2.5 — atalhos do operador (commit `1a6269c`, PR #7)
Decisões do usuário: favoritos **por operador e filial**; endpoint de atalhos já
no formato final (`recent`/`topSelling` vazios até o M3); o fix abaixo vai junto.

| Entrega | Onde |
|---|---|
| Agregado `OperatorFavorites` + `FavoriteProduct` (PK composta); `replace()` compara listas em vez de recriar | `src/Domain/Catalog/` |
| `SetFavoriteProducts` (pede `command.item.add`; produto da filial e ativo) | `src/Application/Catalog/Favorites/` |
| `CatalogQuery::favorites()` + `ShortcutsView` | `src/Application/Catalog/`, `DbalCatalogQuery` |
| `GET /api/branches/{id}/shortcuts`, `PUT /api/branches/{id}/shortcuts/favorites` | `ShortcutsController` |
| `IdentifierForeignKeys`: FKs `product_id`/`user_id`; pula só a referência à própria tabela | `.../Doctrine/Schema/` |
| **Fix (bug do M1.2):** `IdentifierType` aceita string que seja ULID válido — o Doctrine achata a associação da PK composta no UPDATE/DELETE (revogar permissão quebrava no flush) | `.../Doctrine/Type/IdentifierType.php`, `RolePermissionPersistenceTest` |
| Migration operator_favorites, operator_favorite_items | `migrations/Version20261026205421.php` |
| Testes: unit do agregado, API (reordenar, lista por pessoa, inativo some, 403 cozinha, 404 outra empresa) — 136 verdes | `tests/` |

### M3.1 — mesas / locais (commit `fa0f947`, PR #8)
Decisões do usuário para o M3 (2026-10-26): **mesas cadastradas** (comanda de
mesa aponta para `Location`); **quantidade inteira** no MVP; desconto **na
comanda e no item**. Contexto novo `App\Domain\Attendance` ("Atendimento"; a
classe da comanda será `Command`, como no doc 01 — alias onde colidir com o
`Command` do bus).

| Entrega | Onde |
|---|---|
| Agregado `Location` + `LocationType` (TABLE exige `tables`, ROOM exige `hotel`) | `src/Domain/Attendance/` |
| Permissão nova `admin.location.manage` (OWNER/ADMIN/MANAGER) + migration de dados | `Permission`, `DefaultRoles`, doc 06 |
| `CreateLocation`, `UpdateLocation`; read model `AttendanceQuery` → `DbalAttendanceQuery` | `src/Application/Attendance/`, `.../Dbal/` |
| API: `GET/POST /api/branches/{id}/locations`, `PATCH /api/locations/{id}` | `src/Presentation/Http/Attendance/` |
| `app:seed-demo-catalog` também cria Balcão + Mesa 1–10 | `SeedDemoCatalogCommand` |
| Migration `locations` + dados da permissão | `migrations/Version20261026210436.php` |
| Testes: unit de `Location`, API (capability por tipo, rótulo único, 403, 404) — 143 verdes | `tests/` |

### M3.2 — comanda (commit `057f001`, PR #9)
| Entrega | Onde |
|---|---|
| `Product::select()` → `ProductSelection`/`SelectedModifier`: valida variação/modificadores/min-max e devolve preços de agora (linguagem publicada do Catálogo) | `src/Domain/Catalog/` |
| Agregado `Command` + `CommandItem` + `CommandItemModifier`; `BindTarget`/`BindType`, `CommandStatus`, `Quantity`, `BusinessDay` (vira às 05:00 no fuso da filial); eventos do doc 05 em `Event/` | `src/Domain/Attendance/` |
| `RecordsEvents` (agregado registra eventos; outbox grava no M3.3); `Money::times()` | `src/Domain/Shared/` |
| Casos de uso `OpenCommand`, `AddItemToCommand`, `ChangeItemQuantity`, `CancelCommandItem`, `CancelCommand`; porta `DisplayNumberSequence` | `src/Application/Attendance/Command/` |
| `DbalDisplayNumberSequence` (`LAST_INSERT_ID` atômico); `counters` fora do ORM (`schema_filter`) | `.../Dbal/`, `doctrine.yaml` |
| Read model `activeCommands()` / `command()` + views | `AttendanceQuery`, `DbalAttendanceQuery` |
| API: `GET/POST /api/branches/{id}/commands`, `GET /api/commands/{id}`, `POST .../items`, `PATCH .../items/{id}`, `POST .../items/{id}/cancel`, `POST .../cancel` | `CommandController` |
| UNIQUE violado → 409; FK de usuário RESTRICT (inclusive favoritos) | `ApiExceptionListener`, `IdentifierForeignKeys` |
| Migration commands, command_items, command_item_modifiers, counters | `migrations/Version20261026211529.php` |
| Testes: domínio sem banco (8000, totais, cancelar, `close()`), API (aceites do doc 07, permissões, isolamento), concorrência (UNIQUE da mesa, contador) — 160 verdes | `tests/` |

### M3.3 — outbox + auditoria (branch `feat/m3.3-outbox-auditoria`, não commitado)
Decisão do usuário: auditoria como **consumidor do outbox** (doc 05), não síncrona.

| Entrega | Onde |
|---|---|
| Interface `AggregateRoot` (Command implementa); eventos da comanda com `displayNumber`, `itemName`, `actorId` | `src/Domain/Shared/`, `src/Domain/Attendance/` |
| `StoredEvent` (entidade de infraestrutura, mapeamento próprio) + `OutboxListener` (onFlush → mesmo COMMIT) | `src/Infrastructure/Persistence/Outbox/`, `.../Doctrine/Listener/` |
| `RecordedEvent` (evento como dados), porta `OutboxRelay` → `DbalOutboxRelay` (1 evento por transação, `SKIP LOCKED`, para na falha) | `src/Application/Shared/Event/`, `.../Outbox/` |
| `app:outbox:publish [--limit]` | `src/Presentation/Console/OutboxPublishCommand.php` |
| Consumidor `AuditTrail` (ações sensíveis → resumo legível, nome do ator copiado) + porta `AuditLog` → `DbalAuditLog` (`INSERT IGNORE`) | `src/Application/Audit/`, `.../Dbal/` |
| Migration `domain_events` (ORM) + `audit_logs` (à mão, fora do ORM) | `migrations/Version20261026213149.php` |
| Testes: evento no mesmo commit, recusado não grava, relay em ordem + auditoria do cancelamento, idempotência — 164 verdes | `tests/Integration/Attendance/OutboxAndAuditTest.php` |

---

## Próximos passos — M3 (Comanda + Pedido)

1. ~~M3.1 — mesas~~.
2. ~~M3.2 — comanda~~.
3. ~~M3.3 — outbox + audit_log~~.
4. **M3.4 — descontos** (comanda e item; faixa do papel; pendente → gerente autoriza).
5. **M3.5 — pedido** (`Order`) + `SendPendingItemsToKitchen`.
6. **M3.6 — `Idempotency-Key`** nos endpoints de escrita.

## Histórico de próximos passos — M2 (Catálogo)

1. ~~M2.1 — estações e categorias~~.
2. ~~M2.2 — agregado `Product`~~.
3. ~~M2.3 — API de produto~~.
4. ~~M2.4 — busca~~.
5. ~~M2.5 — atalhos do operador~~. **M2 concluído.**
6. **M3 — Comanda + Pedido** (doc 07): o núcleo — `Command`, `Order`, máquinas
   de estado (doc 04), outbox (doc 05). Preencher `recent`/`topSelling` dos atalhos.

## Histórico de próximos passos — M1 sobre Symfony

1. ~~Agregado Company~~ (M1.1). ~~User + Role~~ (M1.2).
2. ~~Autenticação~~ (M1.3).
3. ~~Multiempresa~~ (M1.4).
4. ~~Autorização~~ (M1.5).
5. ~~Seed demo~~ (M1.6). **M1 concluído.**
6. **M2 — Catálogo** (doc 07): `categories`, `products`, `product_variants`,
   `modifier_groups`, `modifiers`, `stations`; CRUD API; busca `FULLTEXT`.
7. **`counters`** (numeração diária de comanda) — fica para o M3, onde é usado.

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
- **`findBy` por associação** com IDs tipados: passar o objeto (`CompanyId`).
  O tipo DBAL aceita string só se for ULID válido (desde o M2.5: o Doctrine
  achata a associação de PK composta para string no UPDATE/DELETE).
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
- **`user_roles.branch_id`** = `NULL` significa "papel na empresa toda" (tem id
  próprio, então não precisa de sentinela; FK para `branches`).
- **Filtro do Doctrine** não pega identity map (`find()` de objeto já carregado
  não vai ao banco) nem DBAL/SQL puro — read model filtra `company_id` à mão.
  Entidade nova: ter a associação `company` ou entrar na lista de filhas do
  `CompanyFilterCoverageTest`.
- **Serviço privado sem consumidor** some do container compilado (o teste não
  acha com `getContainer()->get()`): montar à mão no teste até ter consumidor
  (`CapabilityChecker`).
- **Listener de schema:** usar `postGenerateSchema` (uma vez, no fim). Trocar
  tabela no `postGenerateSchemaTable` faz o ORM recriar o schema e as tabelas
  seguintes perdem o charset (`DEFAULT CHARACTER SET` some da migration).
- **Rota com `Requirement::ULID`**: ID malformado vira 404 antes do controller.
- **FULLTEXT só enxerga dado COMMITADO** (o InnoDB atualiza o índice no
  commit). Teste de busca: `#[SkipDatabaseRollback]` + limpeza no `tearDown`
  (apagar `products` antes da empresa: FK para categoria/estação é RESTRICT).
- **FULLTEXT ignora palavras < 3 letras** (`innodb_ft_min_token_size`) e não acha
  pedaço do meio da palavra (`hop` ≠ Chopp). Se precisar: parser `ngram`.
- **Outbox no Doctrine:** o `onFlush` roda ANTES do `beginTransaction` — INSERT
  direto ali ficaria fora da transação. Por isso o evento vira entidade
  (`StoredEvent`) com `persist` + `computeChangeSet` no próprio `onFlush`.
- **Uma comanda ativa por mesa:** coluna `open_table_ref` mantida pelo agregado
  (NULL quando libera) + UNIQUE — mais simples que coluna gerada no Doctrine.
- **Tabela só de SQL** (`counters`): `schema_filter` no `doctrine.yaml`, senão o
  `make:migration` tenta apagá-la.
- **Teste HTTP + container:** depois de uma requisição, o container do teste
  guarda o contexto/filtro da empresa logada até o próximo request. Preparar
  dados de OUTRA empresa antes da 1ª requisição (no `setUp`).
- **PATCH com "limpar":** DTO não distingue ausente de `null` — o controller
  olha as chaves do JSON (`$request->toArray()`) e passa flags ao comando.
- **Nomes por extenso:** `$entityManager`, `$exception`, `$permission`... nunca
  sílaba. Única exceção combinada: `$db`.
- **Método de trabalho:** explicar cada conceito/padrão ao aplicar; usar as
  ferramentas (Maker, `make:migration`) e revisar o gerado; sempre `composer check`
  antes de commitar. Identidade git local = pessoal (`rodolfo.nakamichi11@gmail.com`).
