# 03 — Modelo de Dados (MySQL 8)

> Convenções gerais (ver ADR-003, 004, 005, 007, 008):
> - Engine **InnoDB**, charset **utf8mb4**.
> - PK = `id CHAR(26)` (ULID). Sem auto-increment nas tabelas de negócio.
> - **Toda** tabela de negócio: `tenant_id CHAR(26) NOT NULL`; quando fizer
>   sentido, `unit_id CHAR(26) NOT NULL`.
> - Dinheiro: `BIGINT` em centavos. Nome sufixo `_cents` quando ajudar.
> - Status: `VARCHAR(30)`.
> - Datas: `DATETIME(6)` (UTC). `created_at`/`updated_at` em todas.
> - Índices únicos sempre **compostos com `tenant_id`**.
> - Soft delete só onde há motivo real; preferimos `status`/`archived_at`.

Este documento é o **contrato do schema**. As migrations (Phinx — ver ADR-011)
devem bater com ele. Os tipos abaixo são um esboço para revisão — não são a
migration final.

---

## 1. Identidade & Acesso

### `tenants`
| Coluna | Tipo | Notas |
|--------|------|-------|
| id | CHAR(26) PK | |
| name | VARCHAR(150) | |
| slug | VARCHAR(80) | `UNIQUE` |
| status | VARCHAR(30) | ACTIVE / SUSPENDED |
| created_at, updated_at | DATETIME(6) | |

### `tenant_capabilities`
| id | CHAR(26) PK |
| tenant_id | CHAR(26) FK → tenants |
| capability | VARCHAR(40) | `tables`, `kitchen`, `inventory`, ... |
| enabled | TINYINT(1) | |
| — | | `UNIQUE (tenant_id, capability)` |

### `units`
| id | CHAR(26) PK |
| tenant_id | CHAR(26) FK |
| name | VARCHAR(150) |
| timezone | VARCHAR(40) | ex. `America/Sao_Paulo` — usado no `display_number` diário |
| status | VARCHAR(30) |
| — | | `UNIQUE (tenant_id, name)` |

### `users`
| id | CHAR(26) PK |
| tenant_id | CHAR(26) FK |
| name | VARCHAR(150) |
| email | VARCHAR(190) |
| password_hash | VARCHAR(255) |
| pin_hash | VARCHAR(255) NULL | login rápido no PWA (4–6 dígitos) |
| status | VARCHAR(30) |
| — | | `UNIQUE (tenant_id, email)` |

### `roles`
| id | CHAR(26) PK | tenant_id | code VARCHAR(30) (`OWNER`,`WAITER`...) | name VARCHAR(80) | `UNIQUE (tenant_id, code)` |

### `role_permissions`
| role_id | CHAR(26) FK | permission VARCHAR(60) | `PRIMARY KEY (role_id, permission)` |

### `user_roles`
| user_id | CHAR(26) FK | role_id | CHAR(26) FK | unit_id CHAR(26) NULL (papel pode ser por unidade) | `PRIMARY KEY (user_id, role_id, unit_id)` |

### `discount_limits`
| id | CHAR(26) PK | tenant_id | role_code VARCHAR(30) | max_percent DECIMAL(5,2) NULL (`NULL` = sem limite) | `UNIQUE (tenant_id, role_code)` |

---

## 2. Catálogo

### `categories`
| id | CHAR(26) PK | tenant_id | unit_id | name VARCHAR(120) | sort_order INT | archived_at DATETIME(6) NULL | `UNIQUE (unit_id, name)` |

### `products`
| Coluna | Tipo | Notas |
|--------|------|------|
| id | CHAR(26) PK | |
| tenant_id, unit_id | CHAR(26) | |
| category_id | CHAR(26) FK NULL | |
| name | VARCHAR(150) | |
| kind | VARCHAR(20) | `PRODUCT` / `SERVICE` (service = Fase 3) |
| base_price_cents | BIGINT NULL | usado se não houver variação |
| station_id | CHAR(26) FK NULL | estação padrão de produção |
| track_stock | TINYINT(1) | se gera baixa de estoque |
| is_active | TINYINT(1) | |
| — | | `INDEX (unit_id, is_active)`, `FULLTEXT (name)` para busca rápida (seção 12) |

### `product_variants`
| id | CHAR(26) PK | product_id FK | name VARCHAR(80) (`300ml`) | price_cents BIGINT | sku VARCHAR(60) NULL | is_active TINYINT(1) | sort_order INT |

### `modifier_groups`
| id | CHAR(26) PK | product_id FK | name VARCHAR(80) (`Ponto`, `Adicionais`) | min_select INT | max_select INT | is_required TINYINT(1) | sort_order INT |

### `modifiers`
| id | CHAR(26) PK | modifier_group_id FK | name VARCHAR(80) | price_delta_cents BIGINT (pode ser 0 ou negativo) | is_active TINYINT(1) | sort_order INT |

### `recipes` / `recipe_items` (capability `inventory`)
`recipes`: | id PK | product_id FK (`UNIQUE`) | yield_qty DECIMAL(10,3) DEFAULT 1 |
`recipe_items`: | id PK | recipe_id FK | stock_id CHAR(26) FK → stocks | qty DECIMAL(10,3) | unit VARCHAR(10) |

### `stations` (capability `kitchen`)
| id | CHAR(26) PK | tenant_id | unit_id | name VARCHAR(60) (`COZINHA`,`BAR`) | is_active TINYINT(1) | `UNIQUE (unit_id, name)` |

---

## 3. Atendimento / Comanda

### `locations`
| id | CHAR(26) PK | tenant_id | unit_id | type VARCHAR(20) (`TABLE`,`COUNTER`,`ROOM`,`EVENT_AREA`) | label VARCHAR(60) (`Mesa 12`) | qr_code VARCHAR(40) | is_active TINYINT(1) | `UNIQUE (tenant_id, qr_code)` |

### `customers`
| id | CHAR(26) PK | tenant_id | name VARCHAR(150) | phone VARCHAR(20) NULL | document VARCHAR(20) NULL | `INDEX (tenant_id, phone)` |

### `commands`
| Coluna | Tipo | Notas |
|--------|------|------|
| id | CHAR(26) PK | |
| tenant_id, unit_id | CHAR(26) | |
| display_number | INT | sequencial por unidade/dia (ver §7) |
| business_date | DATE | data operacional (fecha às 05:00 local, config) |
| bind_type | VARCHAR(20) | MESA, BALCAO, CLIENTE, QUARTO, PULSEIRA, EVENTO, VEICULO, SERVICO, AVULSO |
| bind_ref | VARCHAR(60) NULL | location_id / customer_id / código pulseira / texto |
| customer_id | CHAR(26) FK NULL | |
| status | VARCHAR(30) | OPEN, AWAITING_PAYMENT, PARTIALLY_PAID, PAID, CLOSED, CANCELLED |
| opened_by | CHAR(26) FK users | |
| opened_at | DATETIME(6) | |
| closed_at | DATETIME(6) NULL | |
| subtotal_cents | BIGINT | denormalizado, recalculado a cada mudança |
| discount_total_cents | BIGINT | |
| service_fee_cents | BIGINT DEFAULT 0 | "10% do garçom" (opcional) |
| total_cents | BIGINT | |
| paid_total_cents | BIGINT | |
| balance_cents | BIGINT | `total − paid_total` |
| cancel_reason | VARCHAR(255) NULL | |
| — | | `UNIQUE (unit_id, business_date, display_number)` |
| — | | `INDEX (unit_id, status)` — listar comandas abertas |
| — | | `INDEX (tenant_id, bind_type, bind_ref)` — achar comanda da mesa |

> Regra: para `bind_type = MESA`, no máximo **uma** comanda `OPEN` por
> `bind_ref`. Garantido pela aplicação + índice parcial emulado (coluna
> gerada `open_bind_key = IF(status='OPEN' AND bind_type='MESA', bind_ref, NULL)`
> com `UNIQUE (unit_id, open_bind_key)`).

### `command_items`
| Coluna | Tipo | Notas |
|--------|------|------|
| id | CHAR(26) PK | |
| tenant_id | CHAR(26) | |
| command_id | CHAR(26) FK | |
| order_id | CHAR(26) FK NULL | pedido que originou (MVP: sempre preenchido) |
| product_id | CHAR(26) FK | |
| product_variant_id | CHAR(26) FK NULL | |
| product_name | VARCHAR(150) | **cópia** no momento do lançamento |
| unit_price_cents | BIGINT | **cópia** (não muda se o cardápio mudar) |
| quantity | DECIMAL(10,3) | |
| modifiers_total_cents | BIGINT | soma dos deltas |
| line_total_cents | BIGINT | `(unit_price + modifiers_total) * quantity` |
| notes | VARCHAR(255) NULL | |
| status | VARCHAR(30) | ACTIVE, CANCELLED, PENDING_AUTHORIZATION |
| cancelled_by | CHAR(26) NULL | |
| cancel_reason | VARCHAR(255) NULL | |
| — | | `INDEX (command_id, status)` |

### `command_item_modifiers`
| id PK | command_item_id FK | modifier_id CHAR(26) FK | modifier_name VARCHAR(80) (cópia) | price_delta_cents BIGINT (cópia) |

### `discounts`
| id | CHAR(26) PK | tenant_id | command_id FK | scope VARCHAR(10) (`ITEM`/`COMMAND`) | command_item_id CHAR(26) FK NULL | type VARCHAR(10) (`PERCENT`/`AMOUNT`) | value DECIMAL(10,2) | amount_cents BIGINT (valor calculado) | status VARCHAR(20) (`APPLIED`/`PENDING`/`REJECTED`) | requested_by CHAR(26) | authorized_by CHAR(26) NULL | reason VARCHAR(255) |

### `bill_splits`
| id | CHAR(26) PK | command_id FK (`UNIQUE`) | strategy VARCHAR(15) (`EQUAL`,`BY_ITEM`,`BY_AMOUNT`,`BY_PERCENT`) | parts JSON | created_by CHAR(26) | created_at |
`parts` (JSON): `[{ "label": "João", "amount_cents": 5800, "item_ids": [...] }, ...]`

---

## 4. Pedidos & Produção

### `orders`
| id | CHAR(26) PK | tenant_id | unit_id | command_id FK | channel VARCHAR(15) | placed_by CHAR(26) NULL | status VARCHAR(20) (CREATED, SENT, PARTIALLY_READY, READY, DELIVERED, CANCELLED) | created_at | sent_at DATETIME(6) NULL | `INDEX (command_id)`, `INDEX (unit_id, status)` |

### `order_items`
| id | CHAR(26) PK | tenant_id | order_id FK | product_id FK | product_variant_id FK NULL | product_name VARCHAR(150) | unit_price_cents BIGINT | quantity DECIMAL(10,3) | notes VARCHAR(255) NULL | station_id CHAR(26) FK NULL | status VARCHAR(20) (PENDING, SENT, IN_PREPARATION, READY, DELIVERED, CANCELLED) | `INDEX (order_id)` |

### `order_item_modifiers`
| id PK | order_item_id FK | modifier_id FK | modifier_name VARCHAR(80) | price_delta_cents BIGINT |

### `production_tickets`
| id | CHAR(26) PK | tenant_id | unit_id | order_id FK | station_id FK | status VARCHAR(20) (RECEIVED, IN_PREPARATION, READY, DELIVERED, CANCELLED) | received_at | started_at NULL | ready_at NULL | delivered_at NULL | `INDEX (station_id, status)` — a query do KDS |

### `production_ticket_items`
| id PK | production_ticket_id FK | order_item_id FK | product_name VARCHAR(150) | quantity DECIMAL(10,3) | modifiers_text VARCHAR(255) | notes VARCHAR(255) NULL | status VARCHAR(20) |

---

## 5. Pagamentos & Caixa

### `payment_methods`
| id | CHAR(26) PK | tenant_id | unit_id | name VARCHAR(60) | kind VARCHAR(15) (CASH, PIX, CARD_DEBIT, CARD_CREDIT, VOUCHER, OTHER) | is_active TINYINT(1) | opens_cash_drawer TINYINT(1) | `UNIQUE (unit_id, name)` |

### `cash_registers`
| id | CHAR(26) PK | tenant_id | unit_id | operator_id CHAR(26) FK | status VARCHAR(10) (OPEN/CLOSED) | opened_at | opening_amount_cents BIGINT | closed_at NULL | counted_amount_cents BIGINT NULL | expected_amount_cents BIGINT NULL | difference_cents BIGINT NULL | `UNIQUE (unit_id, operator_id, status)` quando status=OPEN (emular com coluna gerada) |

### `cash_movements`
| id | CHAR(26) PK | tenant_id | cash_register_id FK | type VARCHAR(15) (SALE, WITHDRAWAL, DEPOSIT, CHANGE, ADJUSTMENT) | amount_cents BIGINT (sinal conforme entrada/saída) | payment_id CHAR(26) FK NULL | reason VARCHAR(255) NULL | created_by CHAR(26) | created_at |

### `payments`
| id | CHAR(26) PK | tenant_id | unit_id | command_id FK | cash_register_id FK NULL | method_id FK | kind VARCHAR(15) | amount_cents BIGINT | status VARCHAR(15) (PENDING, CONFIRMED, FAILED, REFUNDED, CANCELLED) | external_ref VARCHAR(80) NULL | idempotency_key VARCHAR(40) NULL | confirmed_at DATETIME(6) NULL | created_by CHAR(26) NULL | `UNIQUE (tenant_id, idempotency_key)`, `INDEX (command_id, status)`, `INDEX (external_ref)` |

---

## 6. Estoque (capability `inventory`)

### `stocks`
| id | CHAR(26) PK | tenant_id | unit_id | name VARCHAR(120) (`Pão brioche`) | unit VARCHAR(10) (`un`,`kg`,`L`) | balance DECIMAL(12,3) | min_balance DECIMAL(12,3) NULL | allow_negative TINYINT(1) DEFAULT 0 | `UNIQUE (unit_id, name)` |

### `stock_movements`
| id | CHAR(26) PK | tenant_id | stock_id FK | type VARCHAR(15) (IN, OUT, ADJUSTMENT, SALE_CONSUMPTION) | qty DECIMAL(12,3) (sinalizado) | balance_after DECIMAL(12,3) | ref_type VARCHAR(20) NULL (`order_item`) | ref_id CHAR(26) NULL | reason VARCHAR(255) NULL | created_by CHAR(26) NULL | created_at | `INDEX (stock_id, created_at)` |

---

## 7. Auditoria & Eventos (transversal)

### `domain_events` (outbox — ver doc 05)
| Coluna | Tipo | Notas |
|--------|------|------|
| id | CHAR(26) PK | ULID (ordenável) |
| tenant_id | CHAR(26) | |
| aggregate_type | VARCHAR(40) | `command`, `order`, `payment`... |
| aggregate_id | CHAR(26) | |
| name | VARCHAR(60) | `OrderItemAdded` |
| payload | JSON | dados do evento |
| occurred_at | DATETIME(6) | |
| published_at | DATETIME(6) NULL | nulo = ainda não despachado |
| device_id | VARCHAR(40) NULL | origem (offline futuro) |
| — | | `INDEX (published_at)`, `INDEX (aggregate_type, aggregate_id, occurred_at)` |

### `audit_logs`
| id | CHAR(26) PK | tenant_id | unit_id | actor_id CHAR(26) NULL | actor_name VARCHAR(150) | action VARCHAR(60) (`command.discount.authorize`) | target_type VARCHAR(40) | target_id CHAR(26) | summary VARCHAR(255) | metadata JSON NULL | created_at | `INDEX (target_type, target_id)`, `INDEX (tenant_id, created_at)` |

### `idempotency_keys` (opcional; alternativa a coluna por tabela)
| key VARCHAR(40) PK junto com tenant_id | tenant_id | endpoint VARCHAR(120) | response_hash VARCHAR(64) | created_at | `PRIMARY KEY (tenant_id, key)` |

---

## 8. `display_number` (número amigável)

Sequencial **por unidade e por data operacional**. Implementação simples e
segura contra corrida:

```sql
-- tabela de contadores
CREATE TABLE counters (
  tenant_id CHAR(26) NOT NULL,
  unit_id   CHAR(26) NOT NULL,
  scope     VARCHAR(30) NOT NULL,   -- 'command:2026-08-29'
  value     INT NOT NULL DEFAULT 0,
  PRIMARY KEY (tenant_id, unit_id, scope)
) ENGINE=InnoDB;
```

No caso de uso, dentro da transação da abertura da comanda:
`INSERT ... ON DUPLICATE KEY UPDATE value = value + 1` e lê o valor.

---

## 9. Diagrama textual (fallback do Mermaid do doc 02)

```
tenants 1─* units 1─* categories 1─* products 1─* product_variants
                     units 1─* locations
                     units 1─* stations
products *─1 stations
products 1─1 recipes 1─* recipe_items *─1 stocks
units 1─* stocks 1─* stock_movements

tenants 1─* users *─* roles 1─* role_permissions
users *─* roles via user_roles

commands 1─* command_items 1─* command_item_modifiers
commands 1─* discounts
commands 1─1 bill_splits
commands 1─* orders 1─* order_items 1─* order_item_modifiers
orders 1─* production_tickets 1─* production_ticket_items
stations 1─* production_tickets
order_items 1─* production_ticket_items

commands 1─* payments *─1 payment_methods
cash_registers 1─* cash_movements
payments 1─0..1 cash_movements

* ─ domain_events (por aggregate_id)
* ─ audit_logs (por target_id)
```

---

## 10. Índices críticos (não esquecer)

| Query real | Índice |
|------------|--------|
| Listar comandas abertas da unidade | `commands (unit_id, status)` |
| Achar comanda aberta da mesa X | `commands (tenant_id, bind_type, bind_ref)` + regra de unicidade |
| KDS de uma estação | `production_tickets (station_id, status)` |
| Busca de produto por nome | `products FULLTEXT(name)` |
| Timeline da comanda | `domain_events (aggregate_type, aggregate_id, occurred_at)` |
| Worker do outbox | `domain_events (published_at)` |
| Conciliação do caixa | `cash_movements (cash_register_id)`, `payments (command_id, status)` |
| Idempotência de pagamento | `payments (tenant_id, idempotency_key) UNIQUE` |

---

## Histórico

| Data | Mudança |
|------|---------|
| 2026-08-29 | Versão inicial. |
| 2026-08-29 | Contrato passa a ser materializado por migrations Phinx (ADR-011), não Laravel. |
