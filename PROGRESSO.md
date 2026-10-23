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
- **Milestone atual:** **M1** sobre Symfony. M1.1 (agregado `Company`) pronto,
  não commitado. Próximo: **M1.2 — agregado `User` + `Role`**.
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

### M1.1 — agregado Company (pronto, aguardando commit)
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

---

## Próximos passos — M1 sobre Symfony

1. ~~Agregado Company~~ (M1.1).
2. **M1.2 — `User` + `Role`:** `User` (raiz) com coleção `UserRole`
   (`id` próprio, `branch_id` NULL = empresa toda — doc 03 atualizado);
   `Role` com permissões (`role_permissions`); `discount_limits`; e-mail único
   por empresa; senha/PIN com `PasswordHasher` do Symfony atrás de uma porta.
3. **`counters`** (numeração diária de comanda) — pode ficar para o M3, onde é usado.
4. **Repositórios:** `UserRepository`, `RoleRepository` + adapters Doctrine.
5. **Multiempresa:** filtro SQL do Doctrine (`CompanyFilter`) ativado com o
   `CompanyContext` da requisição; mover `CompanyContextHolder` para
   `Application` e implementar `ResetInterface` (seguro em worker).
6. **Auth:** Symfony Security com `access_token` authenticator (hash sha256 em
   `api_tokens`); `/health` público, `/api/*` protegido.
7. **Permissões:** Voters a partir de `user_roles`/`role_permissions` (doc 06);
   `CapabilityChecker`.
8. **Seed:** comando `app:seed-demo` (ou DoctrineFixturesBundle).
9. **Aceite:** teste de isolamento A×B, `WAITER` 403 / `CASHIER` 200,
   `hotel` = false. CI ganha `services: mysql`.

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
- **Doctrine + PHP 8.3:** entidade mapeada não pode ser `final` (proxy).
- **Domain fora do container** → interface de porta precisa de alias explícito
  em `config/services.yaml`, senão "Cannot autowire ... has been excluded".
- **DATETIME(6):** criar só um tipo novo deixava o diff eterno (introspecção lê
  DATETIME). Solução: sobrescrever `datetime`/`datetime_immutable` do DBAL.
- **Recipes contrib** (ex.: DAMA) não rodam (`allow-contrib: false`): registrar
  bundle em `config/bundles.php` e config à mão.
- **DBAL 4:** `ConversionException::conversionFailed` não existe mais →
  `Types\Exception\ValueNotConvertible::new()`.
- **PHPStan** lê o container compilado (`var/cache/dev/...xml`): rodar
  `bin/console cache:warmup` antes se o cache estiver limpo. Cache velho depois
  de `composer require` → `vendor/bin/phpstan clear-result-cache`.
- **`user_roles.branch_id`** = `CHAR(26) NOT NULL DEFAULT ''` — `''` significa
  "papel na empresa toda". Sentinela em vez de NULL (PK não aceita NULL). Sem FK.
- **Método de trabalho:** explicar cada conceito/padrão ao aplicar; usar as
  ferramentas (Maker, `make:migration`) e revisar o gerado; sempre `composer check`
  antes de commitar. Identidade git local = pessoal (`rodolfo.nakamichi11@gmail.com`).
