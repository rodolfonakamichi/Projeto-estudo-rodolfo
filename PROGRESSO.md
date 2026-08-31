# Progresso da implementação — ponto de retomada

> Atualizado em 2026-08-30. Este arquivo é o "save game": estado exato,
> próximos passos e decisões aprendidas. Ler junto com `docs/dominio/07-roadmap-mvp.md`.
> **Ao retomar: ler este arquivo primeiro**, depois `git switch m1-identidade` e
> `docker compose up -d`.

---

## Onde estamos

- **Branch de trabalho:** `m1-identidade` (criada a partir da `main`).
- **`main`** = commit `c445eae` (M0 completo + M1.a + rename Tenant→Company), pushado.
- **Milestone atual:** M1 — Identidade, multiempresa, permissões.
- **Sub-passo em aberto:** **M1.e.2** (entidade `User` + repositório) — falta o
  usuário criar `User.php`, `UserRepository.php`, `PdoUserRepository.php` e a
  linha de binding no `config/container.php` (código pronto na conversa).

### ⚠️ Há trabalho NÃO COMMITADO na branch `m1-identidade`

Antes de desligar, rodar:
```bash
git add -A
git commit -m "M1.b-e1: migrations identidade + VOs Ulid/Identifier + PdoRepository base"
```
Sem isso, o trabalho abaixo se perde. (O banco de dev pode ser recriado com
`phinx migrate`, mas os arquivos não.)

Arquivos novos não commitados:
- `database/migrations/2026083101*..2026083102*` — **10 migrations** (M1.b + M1.c)
- `src/Domain/Shared/Ulid.php`
- `src/Domain/Identity/` — `Identifier.php`, `CompanyId.php`, `UserId.php`,
  `BranchId.php`, `CompanyContext.php`, `CompanyContextHolder.php`
- `src/Infrastructure/Persistence/Pdo/PdoRepository.php`
- `tests/Domain/Identity/IdentifierTest.php`
- docs 00–07 modificados (rename Company/Branch), `composer.json`/`lock`
  (symfony/uid), `README.md`

---

## Como retomar o ambiente

```bash
cd ~/PhpstormProjects/Projeto-estudo-rodolfo
git switch m1-identidade
docker compose up -d
docker compose exec php php bin/console app:health     # OK / PHP 8.3.33 / MySQL 8.4.11
docker compose exec php vendor/bin/phinx status        # 10 migrations "up"
docker compose exec php vendor/bin/phpunit             # 12 tests verdes
```
Se o banco estiver vazio (volume recriado): `docker compose exec php vendor/bin/phinx migrate`.

Checks (rodar antes de cada commit):
```bash
docker compose exec php vendor/bin/php-cs-fixer fix
docker compose exec php vendor/bin/phpstan analyse
docker compose exec php vendor/bin/phpunit
```

---

## Feito

### M0 — Fundação (na `main`, CI verde)
| Passo | Entrega |
|---|---|
| M0.1 | Docker isolado: `docker-compose.yml` (name `comandas-universal`), php-fpm 8.3 + nginx (8080) + mysql 8.4 (33061). `docker/` |
| M0.2 | Camadas `src/{Domain,Application,Infrastructure,Presentation}` + `tests/`. VO `Money` (TDD, 8 testes) |
| M0.3 | php-cs-fixer (`.php-cs-fixer.php`) + PHPStan L6 (`phpstan.neon`) + `.editorconfig` |
| M0.4 | Kernel HTTP à mão: `SapiEmitter`, `Router` (FastRoute), `Pipeline` (PSR-15), `JsonResponse`; `HealthController`; `config/routes.php`; `public/index.php` |
| M0.5 | Container php-di (`config/container.php`), `bin/console` (symfony/console) + comando `app:health` |
| M0.6 | Phinx (`phinx.php`, `database/`), `.env` no PHP (`config/env.php`, `config/bootstrap.php`, vlucas/phpdotenv) |
| M0.7 | CI GitHub Actions (`.github/workflows/ci.yml`): composer validate, encoding UTF-8, cs-fixer, phpstan, phpunit |

### M1 (branch `m1-identidade`, NÃO commitado)
| Passo | Entrega |
|---|---|
| M1.a | `PDO` no container (factory lê `$_ENV`); `HealthCommand` checa MySQL |
| M1.b | Migrations `companies`, `branches`, `company_capabilities`, `counters` |
| M1.c | Migrations `users`, `roles`, `role_permissions`, `user_roles`, `discount_limits`, `api_tokens` |
| M1.d | VOs `Ulid`, `Identifier` (base), `CompanyId`/`UserId`/`BranchId`, `CompanyContext`. `IdentifierTest` (4 testes) |
| M1.e.1 | `CompanyContextHolder` (mutável, singleton) + `PdoRepository` base (helpers `fetchOneScoped`/`fetchAllScoped` que forçam `company_id`) |

---

## Próximos passos (em ordem)

### M1.e.2 — entidade `User` + repositório  ← RETOMAR AQUI
Criar (código pronto na conversa, mensagem "M1.e.2 — entidade User + repositório"):
- `src/Domain/Identity/User.php` — entidade (id, companyId, name, email,
  passwordHash, pinHash, status; `verifyPassword`, `verifyPin`, `isActive`)
- `src/Domain/Identity/UserRepository.php` — interface (`findById`, `findByEmail`)
- `src/Infrastructure/Persistence/Pdo/PdoUserRepository.php` — extends
  `PdoRepository` implements `UserRepository`, com `hydrate()`
- `config/container.php` — binding
  `UserRepository::class => \DI\autowire(PdoUserRepository::class)`

### M1.e.2b — teste de isolamento entre empresas
- `tests/Support/DatabaseTestCase.php` — base: PDO real, cada teste numa
  transação com rollback; helper pra setar o `CompanyContextHolder`
- `tests/Infrastructure/Persistence/PdoUserRepositoryTest.php` — insere 2
  empresas + usuários; `findByEmail` com contexto da empresa A **não** acha o
  usuário da empresa B (critério de aceite M1)
- CI: adicionar `services: mysql` ao `.github/workflows/ci.yml` (testes agora
  dependem de banco). Ver `.github/workflows/README.md`.

### M1.f — autenticação por bearer token
- Middleware PSR-15 `AuthMiddleware` em `src/Infrastructure/Http/`:
  lê `Authorization: Bearer <token>` → `hash('sha256', $token)` → busca em
  `api_tokens` (não revogado, não expirado) → resolve `user_id` + `company_id`
  → `CompanyContextHolder->set(new CompanyContext(...))`
- Sem token / inválido → 401 JSON
- Registrar o middleware no `Pipeline` (via `config/container.php`, a factory
  do `Pipeline` passa a receber `[AuthMiddleware]`)
- Rota `/health` deve continuar pública → o middleware ignora rotas numa
  allowlist, ou o pipeline só cobre `/api/*`. Decidir.
- `TokenRepository` / `PdoTokenRepository` (ou método no repo de auth)
- Comando `bin/console` pra gerar token de um usuário (útil pro dev/seed)

### M1.g — `PermissionChecker` + `CapabilityChecker`
- `PermissionChecker::assert(User $u, string $permission, ?BranchId $b): void`
  — lê `user_roles` + `role_permissions`; 403 (exceção) se faltar.
  Usado nos **casos de uso** (`Application`), não nos controllers/entidades.
- `CapabilityChecker::has(CompanyId $c, string $capability): bool` — lê
  `company_capabilities`. Cache por requisição.
- `DiscountPolicy::maxPercentFor(array $roleCodes): ?float` — lê `discount_limits`.

### M1.h — seeder
- `database/seeds/` via Phinx `SeedCommand`:
  1 empresa demo + capabilities (`tables`, `kitchen`, `inventory`)
  1 filial
  9 papéis + matriz de permissões do `docs/dominio/06-matriz-de-permissoes.md`
  1 usuário por papel (senha conhecida) + `api_tokens` pra cada
  `discount_limits` padrão (WAITER 5%, CASHIER 10%, MANAGER 20%)

### M1.i — testes de aceite (fecham o M1)
- Isolamento: usuário da empresa A não vê dado da empresa B
- `WAITER` → 403 em `command.close`; `CASHIER` → 200
- `CapabilityChecker` retorna `false` para `hotel` na empresa demo

### Depois do M1
- Merge `m1-identidade` → `main`
- M2 (Catálogo), M3 (Comanda+Pedido), ... ver `docs/dominio/07-roadmap-mvp.md`

---

## Decisões e aprendizados (não repetir os erros)

- **Nomes:** `Company` (não `Tenant`), `Branch` (não `Unit`). `company_id`,
  `branch_id`. "multiempresa", "filial" na prosa. Preservado: `unit_price` e
  `stocks.unit`/`recipe_items.unit` (medida un/kg/L).
- **Encoding:** só UTF-8. O PhpStorm do usuário salva em ISO-8859-1 por padrão —
  conferir Settings → Editor → File Encodings tudo UTF-8. `.editorconfig` força,
  CI verifica. Se um `.md`/`.php` sair com `neg�cio`, converter:
  `iconv -f ISO-8859-1 -t UTF-8 arquivo -o arquivo`.
- **Phinx 0.16:** coluna é **nullable por padrão** se não passar `'null' => false`.
  Parte de PK nullable → erro MySQL `1171`. Sempre `'null' => false` explícito.
- **PHPStan result cache** (`/tmp/phpstan` no container) fica velho depois de um
  `composer require` — `vendor/bin/phpstan clear-result-cache`.
- **`composer.lock`** tem hash do `composer.json` — mudar `name`/`description`
  exige `composer update --lock` (só sincroniza, não mexe em pacote).
- **`#[AsCommand]`** do symfony/console: se o cs-fixer remover o `use ...AsCommand`
  (quando o atributo não estava presente), o comando fica sem nome. Manter o import.
- **Padrão de migration** (todas seguem):
  ```php
  $this->table('x', ['id' => false, 'primary_key' => ['id']])
      ->addColumn('id', 'char', ['limit' => 26, 'null' => false])
      ->addColumn('company_id', 'char', ['limit' => 26, 'null' => false])
      // ... 'null' => false em tudo que não é opcional
      ->addTimestamps()
      ->addIndex(['company_id', '...'], ['unique' => true])
      ->addForeignKey('company_id', 'companies', 'id', ['delete' => 'CASCADE'])
      ->create();
  ```
- **`user_roles.branch_id`** = `CHAR(26) NOT NULL DEFAULT ''` — `''` significa
  "papel na empresa toda". Sentinela em vez de NULL (PK não aceita NULL). Sem FK.
- **Contexto por requisição:** container constrói serviços 1×; `CompanyContext`
  só existe no meio da requisição → `CompanyContextHolder` mutável (o middleware
  seta, os repos leem). Seguro no modelo PHP-FPM (1 processo/requisição).
- **Método de trabalho:** ir devagar, explicar cada conceito, usuário escreve
  código quando quer / Claude escreve quando pedido, sempre rodar os 3 checks.
  Trabalhar direto na branch (sem PR por milestone). Identidade git local =
  pessoal (`rodolfo.nakamichi11@gmail.com`).

---

## Estado do banco (dev)

10 tabelas + `phinxlog`, todas as migrations `up`, 100% reversíveis
(`phinx rollback --target=0` → `phinx migrate`).

```
companies · branches · company_capabilities · counters
users · roles · role_permissions · user_roles · discount_limits · api_tokens
```
FKs: tudo aponta pra `companies`/`users`/`roles` com `ON DELETE CASCADE`.
PK composta: `user_roles(user_id, role_id, branch_id)`,
`role_permissions(role_id, permission)`, `counters(company_id, branch_id, scope)`.
