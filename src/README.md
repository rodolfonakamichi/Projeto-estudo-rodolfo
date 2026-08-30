# `src/` — Código da aplicação

Arquitetura em camadas montada à mão, sem framework (ADR-002 / ADR-011).
Autoload PSR-4: `App\` → `src/`. O caminho do arquivo **é** o namespace
(sensível a maiúsculas): `App\Domain\Shared\Money` → `src/Domain/Shared/Money.php`.

## Regra de dependência

```
Presentation ──▶ Application ──▶ Domain ◀── Infrastructure
```

- `Domain` não importa **nada** das outras camadas nem de pacote de terceiros.
- `Application` depende só de `Domain`.
- `Infrastructure` implementa interfaces definidas em `Domain`/`Application`.
- `Presentation` depende de `Application`.

Uma seta que aponta na direção errada é bug de arquitetura.

## Camadas

### `Domain/`
Regras de negócio puras: entidades, value objects, eventos de domínio, interfaces
de repositório, máquinas de estado. Zero dependência de framework, banco ou
biblioteca. Testável sem infraestrutura.

- `Domain/Shared/` — o que é usado por vários agregados: `Money` (VO de dinheiro,
  imutável, centavos + moeda, com `allocate()` para divisão de conta), e adiante
  `Ulid`, `TenantId`, a interface `DomainEvent`.
- Adiante: `Domain/Catalog/`, `Domain/Command/`, `Domain/Order/`,
  `Domain/Payment/`, `Domain/Production/`, `Domain/Inventory/`.

### `Application/`
Casos de uso — **uma classe por ação** (`OpenCommand`, `AddItemToCommand`,
`SendOrderToKitchen`). Orquestra entidades e repositórios, checa permissão,
abre transação. Não tem regra de negócio (isso é `Domain`) nem SQL (isso é
`Infrastructure`).

### `Infrastructure/`
Implementações concretas do que o `Domain`/`Application` declaram como interface:

- `Infrastructure/Http/` — o **kernel HTTP** montado à mão: adapta o request PHP
  para PSR-7 (`SapiEmitter`), roteia (`Router` sobre FastRoute), encadeia
  middlewares (`Pipeline`, PSR-15). Substitui o que um framework HTTP daria pronto.
- Adiante: `Infrastructure/Persistence/Pdo/` (repositórios com PDO, mapeadores
  linha ⇄ entidade), gateway PIX, publicação de eventos (outbox).

### `Presentation/`
Entrada/saída HTTP:

- `Presentation/Http/` — **controllers**, um por endpoint. Cada um é um
  `RequestHandlerInterface` (PSR-15): lê o request, chama o caso de uso, monta o
  response JSON. É aqui que mora "a API" (o kernel em `Infrastructure/Http` é só
  o encanamento).

## Fora de `src/`

| Caminho | O quê |
|---------|-------|
| `public/index.php` | front controller — único arquivo PHP acessível pela web |
| `config/` | `routes.php` e (adiante) container, parâmetros |
| `tests/` | PHPUnit, espelha `src/`, namespace `Tests\` |
| `bin/console` | CLI (adiante, M0.5) |
| `database/` | migrations e seeds do Phinx (adiante, M0.6) |
