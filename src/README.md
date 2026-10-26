# `src/` — Código da aplicação

Symfony 7.4 LTS organizado em camadas hexagonais (ADR-002 / ADR-012).
Autoload PSR-4: `App\` → `src/`. O caminho do arquivo **é** o namespace
(sensível a maiúsculas): `App\Domain\Shared\Money` → `src/Domain/Shared/Money.php`.

## Regra de dependência

```
Presentation ──▶ Application ──▶ Domain ◀── Infrastructure
```

- `Domain` não importa **nada** das outras camadas nem do framework
  (exceção única: `symfony/uid`, embrulhado em `Domain\Shared\Ulid`).
- `Application` depende só de `Domain`. Declara **portas** (interfaces).
- `Infrastructure` implementa as portas do `Domain`/`Application` (**adapters**).
- `Presentation` depende de `Application` (e lê tipos do `Domain`).

Verificado pelo **Deptrac** (`deptrac.yaml`, roda no CI). Seta na direção
errada quebra o build.

## Camadas

### `Domain/`
Regras de negócio puras: entidades, value objects, eventos de domínio,
interfaces de repositório, máquinas de estado. PHP puro, testável sem kernel.
**Fora do container** do Symfony (`config/services.yaml` exclui a pasta):
entidade e VO nascem com `new`/named constructor, nunca por injeção.

- `Domain/Shared/` — `Money`, `Ulid`, `Identifier` (base dos IDs tipados),
  `DomainException`, interfaces `DomainEvent` e `Timestamped`.
- `Domain/Identity/` — agregado `Company` (+ `Branch`, `CompanyCapability`),
  `Slug`, enums de status/capability, IDs tipados, `CompanyContext`, porta
  `CompanyRepository`.
- Adiante: `Catalog/`, `Command/`, `Order/`, `Payment/`, `Production/`, `Inventory/`.

### `Application/`
Casos de uso no padrão **Command + Handler**: `OpenCommand` (DTO com os dados)
+ `OpenCommandHandler` (implementa `CommandHandler`, método `__invoke`). O
handler orquestra entidades e repositórios; não tem regra de negócio (`Domain`)
nem SQL (`Infrastructure`).

- `Application/Shared/Bus/` — portas `CommandBus`, `EventBus` e as interfaces
  marcadoras `Command`, `CommandHandler`, `EventHandler`. Implementar
  `CommandHandler` já registra o handler no `command.bus` (via `_instanceof`).
- `Application/Health/` — porta `DatabaseProbe`.

### `Infrastructure/`
Adapters concretos:

- `Infrastructure/Bus/` — `MessengerCommandBus`, `MessengerEventBus` (Symfony
  Messenger). Cada comando roda numa transação do Doctrine.
- `Infrastructure/Persistence/Doctrine/Mapping/` — mapeamento **XML** das
  entidades do Domain (`Identity.Company.orm.xml` ↔ `App\Domain\Identity\Company`).
- `Infrastructure/Persistence/Doctrine/Type/` — tipos DBAL: IDs tipados ⇄
  CHAR(26), `Slug`, e o override de `datetime*` para DATETIME(6) UTC.
- `Infrastructure/Persistence/Doctrine/Repository/` — adapters dos
  repositórios do Domain (`DoctrineCompanyRepository`). Ligação porta → adapter
  em `config/services.yaml`.
- `Infrastructure/Persistence/Doctrine/Listener/` — `TimestampListener`.
- `Infrastructure/Persistence/Doctrine/Filter/` — `CompanyFilter` (filtro SQL
  `company_id = ?` em toda consulta do ORM, ADR-004).
- `Infrastructure/Security/` — autenticação por token opaco e o
  `CompanyContextHolder` (contexto da empresa + liga o filtro), ativado pelo
  `EnterCompanyContextOnLogin`.
- `Infrastructure/Health/` — `DbalDatabaseProbe`.
- Adiante: gateway PIX, outbox.

### `Presentation/`
Entrada e saída:

- `Presentation/Http/` — controllers (`#[Route]`). Leem o request, montam o
  Command, despacham no `CommandBus`, devolvem JSON.
- `Presentation/Console/` — comandos `bin/console` (`#[AsCommand]`).

## Fora de `src/`

| Caminho | O quê |
|---------|-------|
| `public/index.php` | front controller (Symfony Runtime) |
| `config/` | `services.yaml` (wiring), `packages/*.yaml` (Doctrine, Messenger...), `routes.yaml` |
| `migrations/` | Doctrine Migrations (`make:migration` gera, revisar contra o doc 03) |
| `tests/` | PHPUnit, espelha `src/`, namespace `App\Tests\` |
| `bin/console` | CLI do Symfony |
