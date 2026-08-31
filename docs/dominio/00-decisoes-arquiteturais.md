# 00 — Decisões Arquiteturais (ADRs)

> ADR = *Architecture Decision Record*. Cada bloco registra uma decisão, o
> contexto que levou a ela e as consequências. Serve para lembrar **por que**
> algo foi feito e para reavaliar quando o contexto mudar.

---

## ADR-001 — O core não conhece o segmento do cliente

**Contexto.** A pesquisa (seções 5.1, 48, 60) alerta que amarrar regras ao tipo
de negócio (`if ($businessType === 'restaurant')`) transforma o sistema em um
emaranhado de condicionais e impede a expansão para bares, eventos, hotéis etc.

**Decisão.** O domínio central trabalha com conceitos genéricos
(`Command`, `Order`, `Product`, `Location`, `Payment`, `Station`). O
comportamento específico de segmento é habilitado por **capabilities** na empresa
e por **módulos/estratégias** que se conectam a pontos de extensão.

**Consequências.**
- ✅ Um mesmo motor atende vários segmentos.
- ✅ Novos segmentos = novo módulo, sem tocar no core.
- ⚠️ Exige disciplina: nenhuma tabela/classe do core pode ter coluna
  `business_type`. Se precisar de um `if` por segmento no core, a modelagem está
  errada — o certo é uma capability ou um evento.

### Capabilities previstas (seção 48)

```
tables         → mesas e vínculo mesa↔comanda
kitchen        → produção / KDS / estações
delivery       → canal e fluxo de entrega
appointment    → agenda (salão, barbearia)
commission     → comissão por profissional
cashless       → pulseira/NFC com saldo
inventory      → estoque e ficha técnica
hotel          → vínculo comanda↔quarto, folio, checkout
```

No MVP implementamos **`tables`, `kitchen`, `inventory`**. As outras entram como
constantes reservadas (o código já lê a lista, só não há módulo ainda).

---

## ADR-002 — Arquitetura em camadas, sem framework

**Contexto.** A seção 52 propõe camadas `Domain / Application / Infrastructure /
Presentation`. Não usamos Laravel no dia a dia (ver ADR-011); trazer um framework
grande só para o MVP significaria estudar o framework em vez dos conceitos que
são o objetivo do projeto.

**Decisão.** PHP 8.3 puro, sem framework, com o código organizado em camadas sob
`src/` (autoload PSR-4 pelo Composer):

```
src/
├── Domain/          # entidades, value objects, regras, eventos. PHP puro, zero dependência.
│   ├── Catalog/
│   ├── Command/
│   ├── Order/
│   ├── Payment/
│   ├── Production/
│   ├── Inventory/
│   └── Shared/       # ValueObjects comuns: Money, CompanyId, Ulid
├── Application/     # casos de uso (1 classe por ação): AddItemToCommand, SendOrderToKitchen...
├── Infrastructure/  # implementações: repositórios PDO, gateway PIX, publicação de eventos
│   ├── Persistence/
│   │   ├── Pdo/           # conexão PDO, mapeadores linha ↔ entidade
│   │   └── Repositories/  # PdoCommandRepository, PdoOrderRepository...
│   ├── Http/             # kernel HTTP, middlewares PSR-15, roteador
│   └── ...
└── Presentation/    # Controllers HTTP, serializers (JSON), definição de rotas
    └── Http/
```

Fora de `src/`: `bin/console` (CLI), `database/migrations` e `database/seeds`
(Phinx), `public/index.php` (front controller), `config/`, `tests/`.

**Peças de infra (o que substitui o framework — detalhe fino no doc 10):**

| Necessidade | Escolha | Lib |
|-------------|---------|-----|
| Autoload / pacotes | Composer, PSR-4 | — |
| Roteamento HTTP | tabela de rotas + dispatcher | `nikic/fast-route` |
| Ciclo request/response | PSR-7 + pipeline PSR-15 | `nyholm/psr7`, `nyholm/psr7-server` |
| Injeção de dependência | container com autowiring | `php-di/php-di` |
| Banco | PDO (mysql), SQL na mão nos repositórios | — (ext-pdo) |
| Migrations | ver ADR-011 | `robmorgan/phinx` |
| Identificadores | ver ADR-005 | `symfony/uid` |
| Templates (admin) | server-render simples | `twig/twig` |
| Testes | — | `phpunit/phpunit` |
| Análise estática / estilo | nível 6+ | `phpstan/phpstan`, `friendsofphp/php-cs-fixer` |

**Regra de dependência:** `Presentation → Application → Domain`.
`Infrastructure` implementa interfaces definidas no `Domain`/`Application`.
`Domain` não importa nada das outras camadas nem de pacote de terceiros.

**Consequências.**
- ✅ Regra de negócio testável sem banco e sem bootstrap de framework.
- ✅ Trocar MySQL, gateway de pagamento ou canal de entrega não afeta o `Domain`.
- ✅ Cada peça (roteador, container, migrations) é entendida isoladamente — é o
  ponto do projeto.
- ⚠️ Nós escrevemos o "encanamento" que um framework daria pronto: kernel HTTP,
  bootstrap, wiring do container, resolução do contexto da empresa. Custo aceito.
- ⚠️ Mais arquivos e um mapeamento entidade ↔ linha do banco (sem ORM que
  esconda isso). Também é proposital.

> **Nota de estudo.** Sem ORM, o repositório é quem faz o `SELECT`/`INSERT` e
> converte entre `Domain\Command\Command` (regra) e as linhas das tabelas. Um
> "mapeador" por agregado concentra esse `array ⇄ objeto`. É trabalho manual —
> exatamente o que se quer praticar aqui.

---

## ADR-003 — Banco: MySQL 8

**Decisão.** MySQL 8 (ou MariaDB 10.11+). InnoDB, charset `utf8mb4`,
collation `utf8mb4_0900_ai_ci` (MySQL) / `utf8mb4_unicode_ci` (MariaDB).

**Consequências / cuidados.**
- Colunas `JSON` existem, mas **sem índice funcional automático** — payload de
  evento e snapshots vão em `JSON`; campos consultáveis ficam em colunas próprias.
- Sem tipo nativo `UUID`/`ULID` → ver ADR-005.
- Transações e FKs: sempre InnoDB.
- `ENUM` do MySQL é frágil para evoluir → usaremos `VARCHAR` + validação na
  aplicação para status (ver ADR-007).

---

## ADR-004 — Multiempresa: banco único com coluna discriminadora

**Contexto.** Seção 42: uma empresa tem várias unidades; precisa isolar dados.

**Decisão.** *Single database, shared schema*. **Toda** tabela de negócio tem
`company_id` (e, quando fizer sentido, `unit_id`). Sem ORM não há *global scope*
automático: um objeto imutável `CompanyContext`, resolvido pelo middleware de
autenticação, é injetado em **todo** repositório, e **todo** SQL de negócio
carrega `WHERE company_id = :company_id`. Uma classe base de repositório e um
`QueryBuilder` fino garantem que ninguém esqueça. Chave de acesso à empresa vem
do usuário autenticado (ou do QR Code, para o cliente).

**Consequências.**
- ✅ Simples de operar, backup único, migrações únicas.
- ⚠️ Risco de vazamento entre companies se alguém esquecer o filtro → mitigado por:
  (1) `company_id` obrigatório na assinatura dos métodos de repositório (sem
  default), (2) `company_id` em todos os índices únicos compostos,
  (3) teste automatizado que garante isolamento (M1).
- Reavaliar para *database-per-company* só se um cliente grande exigir.

---

## ADR-005 — Identificadores: ULID

**Decisão.** Chave primária = **ULID** (26 caracteres, `CHAR(26)`), gerado pela
aplicação com `symfony/uid` (`new Symfony\Component\Uid\Ulid()`), encapsulado
num VO `Domain\Shared\Ulid` para o domínio não depender do pacote.

**Por quê ULID e não auto-increment nem UUIDv4:**
- Gerado no cliente/app → permite **idempotência offline** (seção 28): o
  dispositivo cria o ID antes de sincronizar.
- Ordenável por tempo (ao contrário de UUIDv4) → melhor para índice do que UUID
  aleatório no InnoDB.
- Não expõe volume de vendas (ao contrário de auto-increment sequencial).

**Consequências.** `CHAR(26)` ocupa mais que `BIGINT`. Aceitável. Números
"amigáveis" para humanos (comanda #1234 do dia) são um **campo separado**
(`display_number`), sequencial por unidade/dia, não a PK.

---

## ADR-006 — Comanda e Pedido são agregados separados

**Contexto.** Seções 8, 54. A comanda é o "guarda-chuva" financeiro; o pedido é
uma remessa para produção.

**Decisão.**
- **Command** (comanda): acumula o consumo de um vínculo (mesa, cliente,
  pulseira...), controla o total a pagar e o ciclo aberto→fechado.
- **Order** (pedido): um lote de itens lançado de uma vez, com ciclo de vida
  de produção (criado→enviado→pronto→entregue). Pertence a uma comanda.
- **CommandItem** ≠ **OrderItem**: o item aparece na comanda (visão financeira,
  divisão de conta) e no pedido (visão de produção). No MVP eles compartilham a
  mesma linha lógica, mas os documentos os tratam separadamente porque em
  cenários futuros (transferir item de mesa, cancelar só na produção) eles
  divergem.

**Consequências.** Toda operação cruza os dois: "adicionar item" cria/atualiza
um `Order` aberto e reflete o total na `Command`. Isso é um **caso de uso**
(`Application\Command\AddItemToCommand`), não lógica espalhada no controller.

---

## ADR-007 — Status como string + máquina de estados na aplicação

**Decisão.** Colunas de status são `VARCHAR(30)`. As transições válidas vivem em
uma classe de máquina de estados por agregado (ver doc 04). Nada de `ENUM` no
banco, nada de `UPDATE ... SET status` solto.

**Por quê.** Evoluir um `ENUM` no MySQL trava a tabela e é irreversível na
prática. E centralizar as transições em uma classe deixa explícito *quem* pode
fazer *qual* transição e *qual evento* ela dispara.

---

## ADR-008 — Dinheiro é inteiro em centavos

**Decisão.** Todo valor monetário é `BIGINT` representando **centavos** (ou a
menor unidade da moeda). Nunca `FLOAT`/`DOUBLE`. Na aplicação, um Value Object
`Money` (valor inteiro + moeda) encapsula soma, divisão e arredondamento.

**Por quê.** `0.1 + 0.2 != 0.3` em ponto flutuante. Divisão de conta (seção 22)
exige arredondamento controlado — o VO decide quem fica com o centavo restante.

---

## ADR-009 — Offline fica para a Fase 2, mas a API já nasce idempotente

**Contexto.** Seções 27–28: offline é diferencial forte, porém caro.

**Decisão.** MVP é *online-first*. Porém, desde já:
- toda escrita aceita um header `Idempotency-Key` (ULID do cliente);
- toda mutação relevante grava um **evento** (doc 05) — base para sync futuro;
- IDs são gerados pelo cliente (ADR-005).

Assim a Fase 2 adiciona fila local + reconciliação sem reescrever o backend.

---

## ADR-010 — Interfaces: Web Admin + PWA + API + tempo real

**Decisão (MVP).**
- **Web Admin**: cadastro, caixa, relatórios. Server-render com Twig
  (páginas simples) — decidir no doc 10.
- **PWA Garçom** e **PWA KDS**: front separado (Vite + Vue/React) consumindo a
  API REST.
- **Tempo real**: **SSE** (Server-Sent Events) no MVP — um endpoint PHP faz
  polling curto do outbox (`domain_events`) e faz stream dos eventos para o KDS
  e para o garçom. Serve porque o fluxo é quase todo servidor→cliente.
  WebSocket dedicado (`cboden/ratchet` ou o binário Mercure) só entra se
  aparecer necessidade real de canal bidirecional — registrar como ADR então.
- **QR Code**: página pública leve por `location`.

Detalhamento da API e do front fica para os docs 10–14.

---

## ADR-011 — Sem framework; migrations com Phinx

**Contexto.** As primeiras versões dos docs assumiam Laravel 11. Mas o time não
usa Laravel no dia a dia — o trabalho real é com **PDO na mão** em repositórios.
Aprender Laravel só para este estudo desviaria o foco (que é modelagem de
domínio, camadas e o ciclo de vida do schema), e criaria a tentação de resolver
tudo com Eloquent/artisan em vez de entender o que acontece por baixo.

**Decisão.**

1. **Sem framework.** PHP 8.3 puro + Composer. As peças de infra (roteador,
   container, PSR-7/15, templates) são libs isoladas, montadas à mão num kernel
   próprio — ver a tabela na ADR-002.
2. **Persistência com PDO.** Repositórios escrevem SQL. Sem ORM. Um mapeador por
   agregado converte linha ⇄ entidade (ADR-002, nota de estudo).
3. **Migrations com Phinx** (`robmorgan/phinx`). Motivos:
   - Padrão de fato em PHP sem framework; `phinx create / migrate / rollback /
     status / breakpoint` prontos.
   - Migration em PHP (API de schema reversível) **ou** SQL puro no mesmo arquivo
     — dá para praticar os dois.
   - Conexão própria via `phinx.php`, isolada do PDO de runtime — zero conflito.
   - Tabela de controle (`phinxlog`) e ordenação por timestamp já resolvidas.
   - **Descartadas:** *runner próprio* (reimplementar versionamento não é o
     objeto de estudo deste projeto; perde rollback e `status`);
     *doctrine/migrations* (brilha acoplado ao ORM do Doctrine, que gera o diff
     a partir do mapeamento; sem ORM sobra só a API verbosa).

**Convenções de migration.**
- Uma migration por mudança coesa; nome descritivo (`CreateCommandsTable`,
  `AddServiceFeeToCommands`).
- Cada milestone do doc 07 = um bloco de migrations (ver a lista por milestone lá).
- Todo `up()` tem `down()` reversível enquanto estiver em desenvolvimento.
- O doc 03 é o **contrato do schema**: a migration tem que bater com ele;
  divergência é bug.
- Colunas geradas / índices parciais emulados (doc 03 §3, §5) vão como SQL puro
  (`$this->execute(...)`) dentro da migration.
- Seeds de referência (papéis, permissões, company demo) em `database/seeds` via
  Phinx `SeedCommand`; seeds de teste ficam nas fixtures do PHPUnit.

**Consequências.**
- ✅ Uma dependência pequena e focada, sem arrastar um framework junto.
- ✅ O versionamento do schema fica explícito e versionado no git desde o M0.
- ⚠️ Phinx não conhece as entidades — nenhuma geração automática de migration a
  partir do código. É escrita manual (o que aqui é desejável).
- ⚠️ `phinx.php` duplica credenciais de banco → ler do mesmo `.env`/`config` que
  o app usa, não hardcode.

**Reavaliar se:** o projeto passar a exigir um framework HTTP completo por outro
motivo — aí a migration tool do framework provavelmente substitui o Phinx.

---

## O que está EXPLICITAMENTE fora do MVP

| Item | Fase | Já previsto em... |
|------|------|-------------------|
| Delivery, WhatsApp, Kiosk como canais | 3 | doc 01 (`OrderChannel` já é enum extensível) |
| NFC / cashless / pré-pago | 3 | capability `cashless` reservada |
| Agenda / comissão | 3 | capabilities reservadas |
| Hotel / PMS | 3 | capability `hotel` reservada |
| IA (previsão, desperdício, gargalo) | 3 | eventos e timeline alimentam isso depois |
| Offline real (fila + reconciliação) | 2 | ADR-009 |
| Fiscal (NF-e/NFC-e/SAT) | 2/3 | evento `CommandClosed` será o gatilho |

---

## Histórico

| Data | Mudança |
|------|---------|
| 2026-08-29 | Versão inicial. |
| 2026-08-29 | ADR-011: projeto deixa de usar Laravel — PHP puro + PDO, migrations com Phinx. Reescrita da ADR-002 (camadas sem framework), ADR-004 (isolamento de company sem global scope), ADR-005 (`symfony/uid`), ADR-010 (SSE no lugar de Reverb). |
| 2026-08-30 | Renomeado `Tenant` -> `Company` (tabelas `companies`, `company_capabilities`; coluna `company_id`; `CompanyContext`). O termo "multi-tenant" vira "multiempresa". |
