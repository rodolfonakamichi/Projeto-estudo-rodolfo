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
- **Milestone atual:** M0 refeito (pronto) → começar **M1** sobre Symfony.

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

⚠️ O banco de dev ainda tem as 10 tabelas do Phinx + `phinxlog`. Antes da
primeira migration Doctrine, zerar: `docker compose down -v && docker compose up -d`.

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

---

## Próximos passos — M1 sobre Symfony

1. **Entidades** `Company`, `Branch`, `User`, `Role` em `src/Domain/Identity/`
   (PHP puro) + mapeamento XML.
2. **Tipos DBAL** para os IDs tipados (`CompanyIdType` etc., `CHAR(26)`) em
   `Infrastructure/Persistence/Doctrine/Type/`, registrados no `doctrine.yaml`.
3. **Migrations:** `make:migration` → revisar contra o doc 03 (as 10 tabelas
   do M1: companies, branches, company_capabilities, counters, users, roles,
   role_permissions, user_roles, discount_limits, api_tokens).
4. **Repositórios:** interface no Domain (`UserRepository`) + `DoctrineUserRepository`.
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
- **PHPStan** lê o container compilado (`var/cache/dev/...xml`): rodar
  `bin/console cache:warmup` antes se o cache estiver limpo. Cache velho depois
  de `composer require` → `vendor/bin/phpstan clear-result-cache`.
- **`user_roles.branch_id`** = `CHAR(26) NOT NULL DEFAULT ''` — `''` significa
  "papel na empresa toda". Sentinela em vez de NULL (PK não aceita NULL). Sem FK.
- **Método de trabalho:** explicar cada conceito/padrão ao aplicar; usar as
  ferramentas (Maker, `make:migration`) e revisar o gerado; sempre `composer check`
  antes de commitar. Identidade git local = pessoal (`rodolfo.nakamichi11@gmail.com`).
