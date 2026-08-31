# 07 — Roadmap do MVP (Fase 1 — "Motor de Comanda")

> Objetivo da Fase 1 (seção 51): um **núcleo sólido** — abrir comanda, lançar
> pedido, produzir, receber, fechar caixa — com auditoria e eventos desde o
> primeiro dia. Sem offline real, sem delivery, sem IA.
>
> Cada milestone termina com **critério de aceite verificável** (teste
> automatizado + demo manual). Não avance sem o aceite.

---

## Sequência (dependências)

```
M0 Fundação ─▶ M1 Identidade/Tenancy ─▶ M2 Catálogo ─▶ M3 Comanda+Pedido ─▶ M4 Produção/KDS
                                                              │
                                                              ▼
                                        M5 Pagamentos+Caixa ─▶ M6 Fechamento+Timeline
                                                              │
                                                              ▼
                                        M7 Estoque ─▶ M8 PWA Garçom ─▶ M9 PWA KDS ─▶ M10 QR ─▶ M11 Dashboard
```

Estimativa de estudo: cada milestone ≈ 1 a 2 semanas em ritmo de aprendizado.

---

## M0 — Fundação do projeto

**Entregas**
- Projeto PHP 8.3 do zero: `composer init`, `require` das libs da ADR-002,
  MySQL 8 via Docker (`docker-compose.yml` com `mysql:8`).
- Estrutura de pastas da ADR-002 (`src/Domain`, `src/Application`,
  `src/Infrastructure`, `src/Presentation`) + autoload PSR-4 no `composer.json`.
- Kernel HTTP mínimo: `public/index.php` → PSR-7 → pipeline PSR-15 → FastRoute →
  controller. Um endpoint `GET /health` respondendo `200`.
- `bin/console` (CLI mínimo) + container `php-di` configurado em `config/`.
- Phinx instalado: `phinx.php` lendo o mesmo `config`/`.env` do app; `phinx
  status` conecta no banco (ADR-011).
- Ferramentas: PHPStan (nível 6+), php-cs-fixer, PHPUnit, `.editorconfig`.
- CI no GitHub Actions: `php-cs-fixer --dry-run`, `phpstan`, `phpunit`.
- Value Objects base em `src/Domain/Shared`: `Money`, `Ulid`, `CompanyId`.
- ADRs 001–011 versionados (já estão em `docs/dominio/00`).

**Aceite**
- `phpunit` verde no CI.
- `GET /health` retorna `200` pelo kernel próprio.
- `phinx migrate` roda uma migration de teste e `phinx rollback` a desfaz.
- `Money::fromCents(2599)->add(Money::fromCents(1))->cents() === 2600`.
- PHPStan sem erro na pasta `src/Domain`.

---

## M1 — Identidade, Tenancy e Permissões

**Entregas**
- Migrations: `companies`, `company_capabilities`, `branches`, `users`, `roles`,
  `role_permissions`, `user_roles`, `discount_limits`, `counters`.
- Autenticação: **bearer token próprio** (tabela `api_tokens` guardando o hash
  do token; middleware PSR-15 valida e injeta o usuário) + login por
  e-mail/senha (`password_hash`) e por **PIN** (PWA).
- `CompanyContext` (objeto imutável) resolvido pelo middleware de auth e injetado
  em todo repositório; classe base de repositório força `WHERE company_id = ?`
  em todo SQL de negócio (ADR-004).
- `PermissionChecker::assert($user, $permission, $unitId)`.
- Seeder: 1 empresa demo, 1 filial, papéis padrão + matriz do doc 06, 1 usuário
  por papel.
- `CapabilityChecker::has($company, 'kitchen')`.

**Aceite**
- Teste: usuário da empresa A **não** enxerga dado da empresa B (mesmo forçando
  `id` na query).
- Teste: `WAITER` recebe 403 em `command.close`; `CASHIER` recebe 200.
- Teste: `CapabilityChecker` retorna `false` para `hotel` na empresa demo.

---

## M2 — Catálogo

**Entregas**
- Migrations: `categories`, `products`, `product_variants`, `modifier_groups`,
  `modifiers`, `stations`.
- CRUD (API + telas admin) de categoria, produto, variação, modificadores.
- Associação produto → estação.
- Busca rápida por nome (`FULLTEXT`) — endpoint `GET /catalog/search?q=`.
- "Mais vendidos / recentes / favoritos" por operador — só a **estrutura**
  (tabela `operator_shortcuts` ou cálculo simples); ranking real na M11.

**Aceite**
- Criar "Chopp" com variações 300ml/500ml e grupo "Tamanho" obrigatório.
- Criar "X-Burger" com grupo "Ponto" (1 obrigatório) e "Adicionais" (0–5).
- `GET /catalog/search?q=cho` retorna Chopp em < 50 ms com 1k produtos (seed).

---

## M3 — Comanda + Pedido (núcleo)

**O milestone mais importante.** É onde a ADR-006 e o doc 04 viram código.

**Entregas**
- Migrations: `commands`, `command_items`, `command_item_modifiers`,
  `discounts`, `orders`, `order_items`, `order_item_modifiers`, `domain_events`,
  `audit_logs`.
- Entidades de domínio: `Command` (raiz), `CommandItem`, `Order`, `OrderItem`,
  VOs `Money`, `ModifierSelection`, `BindTarget`.
- `CommandStateMachine`, `OrderStateMachine` (doc 04).
- Repositórios PDO + mapeador linha ↔ entidade por agregado.
- Outbox: `Command` acumula eventos; repositório persiste estado + eventos na
  mesma transação.
- Casos de uso (`src/Application/Command`):
  `OpenCommand`, `AddItemToCommand`, `ChangeItemQty`, `CancelItem`,
  `ApplyDiscount`, `RequestDiscount`, `AuthorizeDiscount`,
  `SendPendingItemsToKitchen`.
- Endpoints REST correspondentes, com `Idempotency-Key`.
- `display_number` via `counters` dentro da transação.

**Aceite**
- Abrir comanda MESA 12 → outra tentativa de abrir MESA 12 falha (regra de
  unicidade).
- Adicionar 2× X-Burger com Bacon → `line_total = (3500 + 500) * 2 = 8000`.
- `total_cents` da comanda recalculado a cada mudança.
- Cancelar item → item vira `CANCELLED` (não some), gera `CommandItemCancelled`
  + `audit_log`.
- `WAITER` aplica 3% → OK. Aplica 15% → desconto fica `PENDING`, `total` não
  muda, evento `DiscountRequested`.
- `MANAGER` autoriza → `total` recalcula, evento `DiscountApplied`, `audit_log`
  com os dois nomes.
- `SendPendingItemsToKitchen` cria um `Order` `SENT` e grava
  `OrderSentToKitchen` no outbox.
- Teste de domínio **sem banco**: `Command::close()` lança exceção se
  `balance ≠ 0`.

---

## M4 — Produção / KDS (capability `kitchen`)

**Entregas**
- Migrations: `production_tickets`, `production_ticket_items`.
- Handler de `OrderSentToKitchen` → explode itens por `station_id` → cria
  tickets (`ProductionTicketReceived`).
- `ProductionTicketStateMachine` + casos de uso `StartTicket`, `MarkTicketReady`,
  `MarkTicketDelivered`, `CancelTicket`.
- Notificação em tempo real via **SSE** (ADR-010): stream
  `GET /stream/branch/{id}/station/{id}` lê o outbox e emite novos tickets e
  mudanças de estado.
- Recalcular status do `Order` a partir dos itens (`OrderItemReady` → ... →
  `OrderReady`).
- Carimbos de tempo (`received_at`, `started_at`, `ready_at`, `delivered_at`).

**Aceite**
- Pedido com X-Burger (COZINHA) + Chopp (BAR) → **2** tickets, um por estação.
- Marcar ticket da cozinha "pronto" → evento `ProductionReady`, o stream SSE da
  filial emite o evento, `Order` vira `PARTIALLY_READY`.
- Ambos prontos → `Order` `READY`.
- `KITCHEN` consegue `production.start`; `WAITER` recebe 403.

---

## M5 — Pagamentos + Caixa

**Entregas**
- Migrations: `payment_methods`, `cash_registers`, `cash_movements`, `payments`.
- `PaymentStateMachine`, `CashRegisterStateMachine`.
- Casos de uso: `OpenCashRegister`, `RegisterCashWithdrawal`,
  `RegisterCashDeposit`, `CloseCashRegister` (às cegas),
  `RegisterPayment` (CASH/CARD manual → `CONFIRMED` na hora),
  `CreatePixCharge` (→ `PENDING`), `ConfirmPixPayment` (webhook),
  `RefundPayment`.
- Gateway PIX: **interface** `PixGateway` + implementação **fake** para o MVP
  (simula webhook via endpoint interno). Integração real fica para depois.
- `PaymentConfirmed` → abate `balance` da comanda + gera `CashMovement` se CASH.

**Aceite**
- Sem caixa aberto → `RegisterPayment(CASH)` falha.
- Pagar R$ 30 numa comanda de R$ 50 → comanda `PARTIALLY_PAID`, `balance = 2000`.
- Pagar os R$ 20 restantes → `PAID`, evento `CommandFullyPaid`.
- PIX: cria `PENDING`; simular webhook → `CONFIRMED`; webhook repetido (mesmo
  `txid`) → **não** duplica.
- Fechar caixa: informo contagem R$ 480, esperado R$ 500 → `difference = -2000`,
  `audit_log`, sem quebrar o fechamento.

---

## M6 — Fechamento de comanda + Timeline + Divisão de conta

**Entregas**
- `CloseCommand` (exige `balance = 0` e nenhum ticket pendente) →
  `CommandClosed`.
- `ReopenCommand` (MANAGER + motivo) → `CommandReopened` + `audit_log`.
- `bill_splits`: casos de uso para as 4 estratégias (igual, por item, por valor,
  por percentual) — cada parte paga gera `Payment`.
- **Timeline**: endpoint `GET /commands/{id}/timeline` lendo `domain_events` +
  formatador legível (doc 05 §6).
- Worker do outbox: `bin/console outbox:publish` chamado por cron do SO a cada
  minuto no MVP (depois vira worker residente), marcando `published_at`.

**Aceite**
- Fluxo completo de ponta a ponta (abre → lança → produz → entrega → paga →
  fecha) num teste de integração.
- `GET timeline` devolve a sequência de linhas legíveis na ordem certa.
- Split "por item" de uma comanda de R$ 118 entre João e Maria → 2 pagamentos
  que somam exatamente R$ 118 (VO `Money::allocate` cuida do centavo).
- Reabrir comanda fechada exige `MANAGER` e registra auditoria.

---

## M7 — Estoque (capability `inventory`)

**Entregas**
- Migrations: `stocks`, `stock_movements`, `recipes`, `recipe_items`.
- CRUD de insumos e ficha técnica.
- Handler de `OrderDelivered` (ou `CommandClosed`, decidir) → lê `recipe` dos
  itens → gera `StockMovement(SALE_CONSUMPTION)`.
- `StockLow` quando saldo < mínimo.
- Entradas e ajustes manuais (`stock.movement.create`, `stock.adjust`).

**Aceite**
- Ficha do X-Burger: 1 pão, 150 g carne, 1 queijo. Vender 10 → estoque baixa
  10 pães, 1,5 kg carne, 10 queijos.
- Cancelar item já entregue → movimento de reversão (ou ajuste), com auditoria.
- Saldo de pão cruza o mínimo → evento `StockLow`.

---

## M8 — PWA do Garçom

**Entregas**
- App separado (Vite + Vue/React) consumindo a API. Login por PIN.
- Telas: minhas mesas, comanda (itens + total ao vivo via WebSocket), lançar
  pedido com **favoritos / mais vendidos / recentes / busca** (seção 45),
  modificadores guiados (seção 10), enviar, entregas pendentes.
- Fluxo de lançamento em ≤ 4 toques (seção 4.3): produto → qtd → (modificador se
  houver) → enviar.
- Service worker: cache de catálogo (leitura offline); **escrita ainda exige
  rede** (offline real é Fase 2).

**Aceite**
- Lançar "Chopp 300ml" da tela de favoritos em 3 toques.
- Total da comanda atualiza sozinho quando o caixa registra um pagamento (outro
  dispositivo).
- Com rede caindo na hora de enviar: mensagem clara, item não some, reenvio
  funciona (idempotência).

---

## M9 — PWA do KDS

**Entregas**
- Tela por estação: colunas RECEBIDO / EM PREPARO / PRONTO; card por ticket com
  itens, modificadores, observações, tempo decorrido.
- Ações: iniciar, pronto, entregue.
- Alertas de atraso (seção 16): card fica vermelho quando `now − received_at`
  passa do tempo médio da estação.
- Tempo real via SSE (`EventSource`); reconecta sozinho.

**Aceite**
- Enviar pedido no PWA garçom → card aparece no KDS em < 2 s sem refresh.
- Marcar "pronto" no KDS → garçom recebe notificação no PWA dele.
- Ticket parado 15 min (média 8) → card em estado de alerta.

---

## M10 — QR Code (canal do cliente)

**Entregas**
- `locations` com `qr_code`; página pública `/{branch}/{qr}` → resolve
  Location → Filial → Company (seção 18–19).
- Cardápio público (leitura do catálogo).
- Ações do cliente (seção 17): **pedir**, **chamar garçom**, **pedir a conta**,
  **ver conta**.
- Pedido do cliente cria `Order` com `channel = QR`, `placed_by = null`;
  entra no mesmo fluxo de produção.
- "Chamar garçom / pedir a conta" → notificação para o PWA do garçom.

**Aceite**
- Escanear QR da Mesa 12 → cardápio da filial certa.
- Cliente faz pedido → aparece na comanda da Mesa 12 (cria a comanda se não
  existir) e no KDS.
- "Pedir a conta" → garçom recebe "Mesa 12: quer pagar".

---

## M11 — Dashboard

**Entregas**
- Primeiro nível (seção 43): faturamento do dia, nº de pedidos, ticket médio,
  margem (se estoque ativo).
- Mais vendidos (agora com dado real).
- Alertas: pedidos atrasados, estoque baixo, divergência de caixa.
- Multifilial para OWNER (seção 42).
- Métricas de produção (tempo médio por produto/estação — seção 15).

**Aceite**
- Números do dashboard batem com uma conferência manual no banco (seed
  controlado).
- Alerta de caixa aparece quando há `difference ≠ 0` no dia.

---

## Fora da Fase 1 (lembrete)

Fase 2: offline real, estoque avançado, dashboard rico, divisão de conta
avançada, integração PIX real, fiscal.
Fase 3: delivery, WhatsApp, NFC/cashless, agenda, hotel/PMS, IA, fidelidade,
autoatendimento pleno, API pública, marketplace de integrações.

---

## Como trabalhar cada milestone (método de estudo)

1. Reler o doc de domínio correspondente.
2. Escrever os **testes de aceite** primeiro (mesmo que falhando).
3. Migrations (Phinx) → entidades de domínio → casos de uso → repositórios PDO →
   controllers → tela.
4. Rodar `php-cs-fixer`, `phpstan`, `phpunit`. Commit pequeno por passo.
5. Demo manual do critério de aceite.
6. Atualizar o "Histórico" do doc de domínio se algo mudou.

---

## Histórico

| Data | Mudança |
|------|---------|
| 2026-08-29 | Versão inicial. |
| 2026-08-29 | ADR-011: sem Laravel. M0 reescrito (setup do zero + kernel + Phinx); auth por token próprio; repositórios PDO; Reverb → SSE; scheduler → cron + `bin/console`; toolchain `php-cs-fixer`/`phpunit`. |
| 2026-08-30 | Renomeado `Tenant` -> `Company` (tabelas `companies`, `company_capabilities`; coluna `company_id`; `CompanyContext`). O termo "multi-tenant" vira "multiempresa". |
| 2026-08-30 | Renomeado `Unit` -> `Branch` (tabela `branches`, coluna `branch_id`); "unidade" vira "filial" na prosa. `unit`/`unit_price` de medida/preço preservados. |
