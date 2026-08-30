# 04 — Máquinas de Estado

> Cada agregado com ciclo de vida tem uma **máquina de estados**: o conjunto
> fechado de estados e as transições permitidas entre eles. Nenhum `UPDATE
> status = ...` acontece fora dessa máquina (ADR-007).
>
> Formato de cada transição:
> `ESTADO_ORIGEM --(ação / quem pode / guarda)--> ESTADO_DESTINO  [evento emitido]`

---

## 1. Comanda (`Command`)

```
                 ┌─────────────────────────────────────────────┐
                 ▼                                             │
   (nova) ──▶ OPEN ──▶ AWAITING_PAYMENT ──▶ PARTIALLY_PAID ──┐  │
                 │            │                    │          │  │
                 │            └────────┬───────────┘          │  │
                 │                     ▼                      │  │
                 │                   PAID ──▶ CLOSED          │  │
                 │                                            │  │
                 └──▶ CANCELLED                               │  │
                                                              │  │
        CLOSED ──(reopen / gerente)──────────────────────────┘  │
        PARTIALLY_PAID / AWAITING_PAYMENT ──(add item volta p/ OPEN)┘
```

| Origem | Ação | Quem | Guarda | Destino | Evento |
|--------|------|------|--------|---------|--------|
| — | abrir | WAITER, CASHIER, sistema (QR) | vínculo válido; mesa sem outra comanda OPEN | `OPEN` | `CommandOpened` |
| OPEN | adicionar/editar item | WAITER, CASHIER | — | `OPEN` | `CommandItemAdded` / `CommandItemChanged` |
| OPEN | cancelar item | WAITER (+motivo), CASHIER | item `ACTIVE` | `OPEN` | `CommandItemCancelled` |
| OPEN | aplicar desconto dentro da faixa | conforme faixa (doc 06) | `value ≤ limite do papel` | `OPEN` | `DiscountApplied` |
| OPEN | solicitar desconto acima da faixa | qualquer operador | — | `OPEN` (item/desc. fica `PENDING`) | `DiscountRequested` |
| OPEN | pedir conta | WAITER, cliente (QR) | ≥1 item ativo | `AWAITING_PAYMENT` | `CommandBillRequested` |
| OPEN / AWAITING_PAYMENT | registrar pagamento parcial | CASHIER | `0 < amount < balance` | `PARTIALLY_PAID` | `PaymentConfirmed` |
| OPEN / AWAITING_PAYMENT / PARTIALLY_PAID | registrar pagamento que zera saldo | CASHIER | `paid_total == total` | `PAID` | `PaymentConfirmed`, `CommandFullyPaid` |
| AWAITING_PAYMENT / PARTIALLY_PAID | adicionar novo item | WAITER, CASHIER | — | `OPEN` | `CommandReopenedForItems` |
| PAID | fechar comanda | CASHIER | `balance == 0`; nenhum ticket de produção pendente | `CLOSED` | `CommandClosed` |
| OPEN | cancelar comanda | MANAGER (ou WAITER com permissão) + motivo | sem pagamento `CONFIRMED` | `CANCELLED` | `CommandCancelled` |
| CLOSED | reabrir | MANAGER + motivo | dentro da mesma data operacional | `OPEN` | `CommandReopened` |

**Regras que a máquina protege:**
- Não fecha com `balance ≠ 0`.
- Não adiciona item em comanda `CLOSED` ou `CANCELLED`.
- Cancelamento após pagamento confirmado ⇒ não é cancelamento, é **estorno**
  (`payment.refund`, doc 06) e depois eventual cancelamento.
- Divisão de conta (`bill_splits`) pode ser criada em `AWAITING_PAYMENT` ou
  `PARTIALLY_PAID`; cada parte paga vira um `Payment` vinculado à comanda.

---

## 2. Pedido (`Order`)

O `status` do pedido é **derivado** do estado dos seus itens.

```
CREATED ──▶ SENT ──▶ PARTIALLY_READY ──▶ READY ──▶ DELIVERED
   │          │                                       ▲
   │          └───────────────────────────────────────┘  (pedido só de itens já entregues na hora, ex.: bebida de balcão)
   └──▶ CANCELLED            (qualquer estado antes de DELIVERED, com permissão)
```

| Origem | Ação | Quem | Guarda | Destino | Evento |
|--------|------|------|--------|---------|--------|
| — | criar (ao adicionar itens não enviados) | WAITER, cliente (QR), API | comanda `OPEN` | `CREATED` | `OrderCreated` |
| CREATED | enviar para produção | WAITER, cliente (QR) | ≥1 item | `SENT` | `OrderSentToKitchen` (+ cria `ProductionTicket` por estação) |
| SENT | primeiro item fica pronto | (sistema, via ticket) | — | `PARTIALLY_READY` | `OrderItemReady` |
| PARTIALLY_READY | todos os itens prontos | (sistema) | — | `READY` | `OrderReady` |
| READY | garçom entrega na mesa | WAITER | — | `DELIVERED` | `OrderDelivered` |
| SENT / PARTIALLY_READY | cancelar item | WAITER/KITCHEN + permissão + motivo | item ainda não `DELIVERED` | (recalcula) | `OrderItemCancelled` |
| CREATED / SENT / PARTIALLY_READY | cancelar pedido inteiro | MANAGER + motivo | nenhum item `DELIVERED` | `CANCELLED` | `OrderCancelled` |

**Item de pedido (`OrderItem`)** — máquina espelhada no ticket:
```
PENDING ──▶ SENT ──▶ IN_PREPARATION ──▶ READY ──▶ DELIVERED
                          └──────────────────────▶ CANCELLED
```

---

## 3. Ticket de produção (`ProductionTicket`) — o que roda no KDS

```
RECEIVED ──▶ IN_PREPARATION ──▶ READY ──▶ DELIVERED
    │               │              │
    └───────────────┴──────────────┴──▶ CANCELLED  (com permissão + motivo)
```

| Origem | Ação | Quem | Efeito colateral | Evento |
|--------|------|------|------------------|--------|
| — | criado ao `OrderSentToKitchen` | sistema | `received_at = now()` | `ProductionTicketReceived` |
| RECEIVED | "iniciar" no KDS | KITCHEN, BAR | `started_at = now()` | `ProductionStarted` |
| IN_PREPARATION | "pronto" no KDS | KITCHEN, BAR | `ready_at = now()`; notifica garçom | `ProductionReady` |
| READY | "entregue" (garçom retira) | WAITER, KITCHEN | `delivered_at = now()` | `ProductionDelivered` |
| qualquer (≠ DELIVERED) | cancelar | MANAGER, KITCHEN c/ permissão | registra motivo | `ProductionTicketCancelled` |

**Métricas derivadas (seção 15):**
- tempo de fila = `started_at − received_at`
- tempo de preparo = `ready_at − started_at`
- tempo total = `ready_at − received_at`
- alerta (seção 16): `now() − received_at > tempo_médio_da_estação × fator` e
  ticket ainda não `READY`.

---

## 4. Pagamento (`Payment`)

```
                    ┌─▶ CONFIRMED ─▶ (REFUNDED)
PENDING ──▶  ───────┤
                    ├─▶ FAILED
                    └─▶ CANCELLED     (expirou / operador desistiu)
```

| Origem | Ação | Quem | Guarda | Destino | Evento |
|--------|------|------|--------|---------|--------|
| — | criar pagamento em dinheiro/cartão manual | CASHIER | caixa `OPEN`; `amount > 0` | `CONFIRMED` (imediato) | `PaymentCreated`, `PaymentConfirmed` |
| — | criar cobrança PIX | CASHIER, cliente (QR) | `amount > 0` | `PENDING` | `PaymentCreated` |
| PENDING | webhook do PSP confirma | sistema (webhook) | assinatura válida; `txid` confere | `CONFIRMED` | `PaymentConfirmed` |
| PENDING | webhook falha / expira | sistema | — | `FAILED` | `PaymentFailed` |
| PENDING | operador cancela a cobrança | CASHIER | — | `CANCELLED` | `PaymentCancelled` |
| CONFIRMED | estornar | MANAGER + motivo | dentro da política | `REFUNDED` | `PaymentRefunded` |

**Efeitos ao `CONFIRMED`:**
1. `command.paid_total_cents += amount`; recalcula `balance` e status da comanda.
2. Se `kind = CASH` e há caixa aberto → cria `CashMovement(type=SALE)`.
3. Publica `PaymentConfirmed` (ouvido por Comanda, Auditoria, Analytics).

**Regra de ouro (seção 23):** só a confirmação da **fonte** (webhook do PSP para
PIX; ação do operador para dinheiro/cartão presencial) leva a `CONFIRMED`. A
tela do cliente dizendo "já paguei" **não** confirma nada.

---

## 5. Sessão de caixa (`CashRegister`)

```
(nova) ──▶ OPEN ──▶ CLOSED
```

| Origem | Ação | Quem | Guarda | Destino | Evento |
|--------|------|------|--------|---------|--------|
| — | abrir caixa | CASHIER, MANAGER | operador sem outra sessão `OPEN` na unidade; informa `opening_amount` | `OPEN` | `CashRegisterOpened` |
| OPEN | sangria (retirada) | CASHIER + motivo | `amount ≤ saldo em dinheiro` | `OPEN` | `CashWithdrawal` |
| OPEN | suprimento (reforço) | CASHIER, MANAGER | — | `OPEN` | `CashDeposit` |
| OPEN | fechar caixa | CASHIER, MANAGER | nenhuma comanda com pagamento `PENDING` nesse caixa | `CLOSED` | `CashRegisterClosed` |

**Fechamento às cegas:** operador digita `counted_amount` sem ver o esperado.
Sistema calcula:
`expected = opening + Σ movimentos` e `difference = counted − expected`.
Se `difference ≠ 0` → `audit_log` + alerta no dashboard (seção 43).

---

## 6. Como isso vira código (nota de estudo)

Padrão sugerido — uma classe por máquina, sem lib externa no começo:

```php
// src/Domain/Command/CommandStatus.php
enum CommandStatus: string {
    case Open = 'OPEN';
    case AwaitingPayment = 'AWAITING_PAYMENT';
    case PartiallyPaid = 'PARTIALLY_PAID';
    case Paid = 'PAID';
    case Closed = 'CLOSED';
    case Cancelled = 'CANCELLED';
}

// src/Domain/Command/CommandStateMachine.php
final class CommandStateMachine
{
    /** @var array<string, string[]> destino => origens permitidas */
    private const TRANSITIONS = [
        'AWAITING_PAYMENT' => ['OPEN'],
        'PARTIALLY_PAID'   => ['OPEN', 'AWAITING_PAYMENT'],
        'PAID'             => ['OPEN', 'AWAITING_PAYMENT', 'PARTIALLY_PAID'],
        'CLOSED'           => ['PAID'],
        'CANCELLED'        => ['OPEN'],
        'OPEN'             => ['AWAITING_PAYMENT', 'PARTIALLY_PAID', 'CLOSED'], // reabertura
    ];

    public static function assert(CommandStatus $from, CommandStatus $to): void
    {
        $allowed = self::TRANSITIONS[$to->value] ?? [];
        if (!in_array($from->value, $allowed, true)) {
            throw new InvalidCommandTransition($from, $to);
        }
    }
}
```

A **entidade** `Command` chama `CommandStateMachine::assert(...)` **antes** de
mudar o campo e **depois** registra o evento correspondente. A verificação de
"quem pode" fica no caso de uso (`Application`), usando a matriz do doc 06 — a
máquina cuida só do "de onde para onde".

Quando o número de estados/guardas crescer, dá para migrar para uma lib
agnóstica de framework (`winzou/state-machine` ou `symfony/workflow`) — registre
como ADR.

---

## Histórico

| Data | Mudança |
|------|---------|
| 2026-08-29 | Versão inicial. |
| 2026-08-29 | Libs alternativas trocadas para opções sem framework (ADR-011). |
