# 05 — Eventos de Domínio

> Um **evento de domínio** é um fato consumado, no passado: `OrderItemAdded`,
> `PaymentConfirmed`. Ele é **imutável**, tem um **ID** (ULID) e um
> **timestamp**. Guardar eventos dá de graça: auditoria, timeline (seção 55),
> alimentação do KDS, base para analytics/IA e ponto de partida para o offline
> (seção 28).

---

## 1. Padrão de implementação: Transactional Outbox

O problema clássico: "salvei no banco mas o broadcast/fila falhou" (ou o
inverso). Solução:

```
┌─ transação única ───────────────────────────────┐
│  1. UPDATE commands / INSERT command_items ...    │
│  2. INSERT INTO domain_events (...) published_at=NULL │
└──────────────────────────────────────────────────┘
                     │
     worker assíncrono lê domain_events WHERE published_at IS NULL
                     │
     ├─▶ stream SSE (KDS, mesa)
     ├─▶ handlers internos (timeline, audit_log, estoque)
     └─▶ (futuro) fila externa / integrações
                     │
            UPDATE domain_events SET published_at = now()
```

- **Escrever o evento** faz parte da **mesma transação** da mudança de estado.
- **Despachar** é assíncrono e idempotente (o `id` do evento evita processar 2x).
- Sem framework (ADR-011): `bin/console outbox:publish` chamado por cron do SO a
  cada minuto no MVP; depois um worker residente com polling curto.

> **Nota de estudo.** Não é *event sourcing* completo — o estado atual continua
> nas tabelas normais (`commands`, `orders`...). O outbox é só a trilha de
> fatos. Event sourcing puro (reconstruir estado a partir dos eventos) é uma
> decisão grande; se um dia fizer sentido, vira ADR.

---

## 2. Estrutura do evento

```json
{
  "id": "01J9Z8Q3M7XK8V2H4B0Y6W1C5A",
  "tenant_id": "01J9...TENANT",
  "aggregate_type": "command",
  "aggregate_id": "01J9...CMD",
  "name": "OrderItemAdded",
  "occurred_at": "2026-08-29T22:14:07.123456Z",
  "device_id": "pwa-waiter-07",
  "payload": {
    "order_id": "01J9...ORD",
    "order_item_id": "01J9...ITEM",
    "product_id": "01J9...PROD",
    "product_name": "X-Burger",
    "quantity": 2,
    "unit_price_cents": 3500,
    "modifiers": [{ "id": "01J9...", "name": "Bacon", "price_delta_cents": 500 }],
    "actor_id": "01J9...USER"
  }
}
```

Regras do `payload`:
- **Auto-contido**: quem consome não deveria precisar de outra query para o
  essencial (por isso copiamos `product_name`, `unit_price_cents`).
- **Sem objetos grandes**: referencie por ID o que for volumoso.
- **Versão implícita pelo nome**: se o formato mudar de forma incompatível, o
  novo evento é `OrderItemAddedV2` (não quebra consumidores antigos).

---

## 3. Catálogo de eventos (MVP)

### Comanda
| Evento | Quando | Payload principal | Consumidores |
|--------|--------|-------------------|--------------|
| `CommandOpened` | comanda aberta | `bind_type`, `bind_ref`, `display_number`, `opened_by` | Timeline, Audit |
| `CommandItemAdded` | item lançado na comanda | item + modifiers + preço | Timeline, Analytics |
| `CommandItemChanged` | qtd/observação alterada | `order_item_id`, `old`, `new` | Timeline, Audit |
| `CommandItemCancelled` | item cancelado | `order_item_id`, `reason`, `actor_id` | Timeline, Audit, Estoque (reverter reserva) |
| `DiscountRequested` | desconto acima da faixa | `scope`, `value`, `requested_by` | Notificação (gerente), Audit |
| `DiscountApplied` | desconto efetivado | `amount_cents`, `authorized_by?` | Timeline, Audit, Analytics |
| `DiscountRejected` | gerente negou | `reason` | Timeline, Audit |
| `CommandBillRequested` | "pedir a conta" | `channel` | Notificação (garçom/caixa) |
| `BillSplitCreated` | divisão de conta definida | `strategy`, `parts[]` | Timeline |
| `CommandFullyPaid` | saldo zerou | `total_cents` | Caixa, Analytics |
| `CommandClosed` | comanda encerrada | totais finais, formas de pagamento | Estoque (baixa def.), Fiscal (futuro), Analytics, Audit |
| `CommandReopened` | reaberta pós-fechamento | `reason`, `authorized_by` | Audit (sempre) |
| `CommandCancelled` | comanda cancelada | `reason`, `actor_id` | Audit, Estoque |

### Pedido / Produção
| Evento | Quando | Payload | Consumidores |
|--------|--------|---------|--------------|
| `OrderCreated` | pedido rascunho criado | `command_id`, `channel` | — |
| `OrderSentToKitchen` | itens enviados p/ produção | `items[]` agrupados por `station_id` | Produção (cria tickets), KDS |
| `ProductionTicketReceived` | ticket criado numa estação | `station_id`, `items[]` | KDS (novo card), Métricas |
| `ProductionStarted` | estação iniciou | `ticket_id`, `started_at` | Métricas, Timeline |
| `ProductionReady` | item/ticket pronto | `ticket_id`, `ready_at` | Notificação garçom, KDS, Métricas |
| `ProductionDelivered` | retirado/entregue | `delivered_at` | Timeline, Métricas |
| `OrderItemReady` | um item do pedido pronto | `order_item_id` | Order (recalcula status) |
| `OrderReady` | todos os itens prontos | `order_id` | Notificação |
| `OrderDelivered` | pedido entregue na mesa | `order_id` | Timeline, Estoque (baixa) |
| `OrderItemCancelled` / `OrderCancelled` | cancelamento na produção | `reason` | Audit, Estoque |

### Pagamento / Caixa
| Evento | Quando | Payload | Consumidores |
|--------|--------|---------|--------------|
| `PaymentCreated` | pagamento/cobrança registrado | `kind`, `amount_cents`, `status` | Timeline |
| `PaymentConfirmed` | confirmado (webhook/operador) | `amount_cents`, `kind`, `external_ref?` | Comanda (abate saldo), Caixa, Analytics, Audit |
| `PaymentFailed` | PIX expirou/erro | `reason` | Notificação caixa |
| `PaymentRefunded` | estorno | `amount_cents`, `reason`, `authorized_by` | Comanda, Caixa, Audit |
| `CashRegisterOpened` | abertura de caixa | `opening_amount_cents`, `operator_id` | Audit |
| `CashWithdrawal` / `CashDeposit` | sangria / suprimento | `amount_cents`, `reason` | Audit |
| `CashRegisterClosed` | fechamento | `counted`, `expected`, `difference` | Audit, Dashboard (alerta se ≠ 0) |

### Estoque
| Evento | Quando | Payload | Consumidores |
|--------|--------|---------|--------------|
| `StockMovementRegistered` | qualquer movimento | `stock_id`, `type`, `qty`, `balance_after` | Dashboard |
| `StockLow` | saldo < mínimo após movimento | `stock_id`, `balance`, `min_balance` | Notificação, Dashboard |
| `StockConsumptionDiverged` | consumo real ≠ ficha técnica (Fase 3) | `expected`, `actual`, `delta_pct` | Alerta (seção 36) |

### Catálogo
| Evento | Quando | Consumidores |
|--------|--------|--------------|
| `ProductPriceChanged` | preço de variação alterado | Audit, Analytics |
| `ProductStationChanged` | estação de produção alterada | — |

---

## 4. Convenções de nome

- **PascalCase**, verbo no **particípio passado**: `OrderSentToKitchen`, não
  `SendOrder` nem `order_sent`.
- Prefixo pelo agregado quando ajudar a desambiguar (`CommandItemCancelled` vs
  `OrderItemCancelled` — são cancelamentos em contextos diferentes).
- Um evento descreve **uma** mudança de negócio. "Salvou 3 itens de uma vez" =
  3 × `CommandItemAdded` + 1 × `OrderSentToKitchen`.

---

## 5. Idempotência

| Camada | Mecanismo |
|--------|-----------|
| API (entrada) | header `Idempotency-Key` (ULID do cliente) → `payments.idempotency_key` / tabela `idempotency_keys`. Requisição repetida devolve a **mesma** resposta, não duplica. |
| Evento (saída) | `domain_events.id` único. Handler marca "processei o evento X" (tabela `event_handler_offsets` ou coluna) → reprocessar é no-op. |
| Webhook PIX | `external_ref` (txid) + verificação de assinatura. Webhook repetido não confirma 2×. |
| Offline (Fase 2) | `event_id` gerado no dispositivo; servidor faz `INSERT ... ON DUPLICATE KEY` no `domain_events`. |

---

## 6. Timeline da comanda (seção 55)

Consulta:
```sql
SELECT name, occurred_at, payload
FROM domain_events
WHERE aggregate_type = 'command' AND aggregate_id = ?
ORDER BY occurred_at, id;
```

Um "formatador" transforma cada evento em uma linha legível:

| Evento | Linha exibida |
|--------|---------------|
| `CommandOpened` | `19:02 — Comanda 42 aberta por João (Mesa 12)` |
| `CommandItemAdded` | `19:03 — + 1× Coca-Cola (R$ 8,00)` |
| `OrderSentToKitchen` | `19:04 — Pedido enviado para Cozinha, Bar` |
| `ProductionReady` | `19:12 — X-Burger pronto (Cozinha, 8 min)` |
| `PaymentConfirmed` | `19:40 — Pagamento PIX R$ 58,00 confirmado` |
| `CommandClosed` | `19:40 — Comanda encerrada por Maria` |

Eventos com dado sensível (desconto, cancelamento, reabertura) **também** geram
`audit_log` com `actor_id`.

---

## 7. Como isso vira código (nota de estudo)

```php
// src/Domain/Shared/Event/DomainEvent.php
interface DomainEvent {
    public function name(): string;
    public function aggregateType(): string;
    public function aggregateId(): string;
    public function payload(): array;
    public function occurredAt(): DateTimeImmutable;
}

// A entidade acumula eventos, não os despacha:
$command->addItem($variant, 2, $modifiers, null);
// dentro de addItem():  $this->recordEvent(new CommandItemAdded(...));

// O repositório, ao salvar, persiste entidade + eventos na MESMA transação:
$this->pdo->beginTransaction();
try {
    $this->persistState($command);
    foreach ($command->pullEvents() as $event) {
        $this->outbox->store($event);   // INSERT em domain_events
    }
    $this->pdo->commit();
} catch (\Throwable $e) {
    $this->pdo->rollBack();
    throw $e;
}
```

O despacho (`published_at`) é um processo separado (`bin/console outbox:publish`).
Handlers internos são um dispatcher próprio (`array<string, callable[]>` por
nome de evento); a entrega ao cliente é via SSE (ADR-010).

---

## Histórico

| Data | Mudança |
|------|---------|
| 2026-08-29 | Versão inicial. |
| 2026-08-29 | Despacho do outbox sem framework (ADR-011): `bin/console outbox:publish` + cron, dispatcher próprio, entrega via SSE. |
