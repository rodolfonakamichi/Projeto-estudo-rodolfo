# 06 — Matriz de Permissões

> Modelo: **RBAC** (papel → permissões) + **ABAC leve** para desconto (faixa por
> papel). Papéis e permissões são **por empresa**; um papel pode ser atribuído
> **por filial** (`user_roles.branch_id`). Ver seções 31–32 da pesquisa.

---

## 1. Papéis (`roles.code`)

| Código | Descrição | Escopo típico |
|--------|-----------|---------------|
| `OWNER` | Dono. Tudo, inclusive faturamento da plataforma. | Empresa |
| `ADMIN` | Administra configuração, catálogo, usuários. | Empresa |
| `MANAGER` | Gerente de filial: autoriza desconto/estorno, fecha caixa, cancela. | Filial |
| `CASHIER` | Caixa: pagamentos, sessão de caixa, fechar comanda. | Filial |
| `WAITER` | Garçom: abrir comanda, lançar item, enviar pedido, entregar. | Filial |
| `KITCHEN` | Cozinha: opera KDS da(s) estação(ões) de cozinha. | Estação |
| `BAR` | Bar: opera KDS do bar. | Estação |
| `STOCK` | Estoque: entradas, ajustes, fichas técnicas. | Filial |
| `AUDITOR` | Só leitura: relatórios, timeline, logs. | Empresa |

Papéis são **cumulativos**: um usuário pode ser `WAITER` + `CASHIER`.

---

## 2. Permissões (`role_permissions.permission`)

Formato `contexto.recurso.ação`.

### Comanda
| Permissão | O que libera |
|-----------|--------------|
| `command.open` | Abrir comanda |
| `command.item.add` | Lançar/editar item |
| `command.item.cancel` | Cancelar item **antes** de ir à produção |
| `command.item.cancel_in_production` | Cancelar item já `IN_PREPARATION` |
| `command.discount.apply` | Aplicar desconto dentro da faixa do papel |
| `command.discount.authorize` | Autorizar desconto acima da faixa de outrem |
| `command.bill.request` | Marcar "pedir a conta" |
| `command.split` | Criar/editar divisão de conta |
| `command.close` | Fechar comanda (saldo zero) |
| `command.cancel` | Cancelar comanda inteira |
| `command.reopen` | Reabrir comanda fechada |
| `command.transfer` | Transferir comanda/itens de mesa *(Fase 2)* |

### Pedido / Produção
| Permissão | O que libera |
|-----------|--------------|
| `order.send` | Enviar pedido para produção |
| `order.cancel` | Cancelar pedido |
| `production.start` | Marcar "em preparação" no KDS |
| `production.ready` | Marcar "pronto" no KDS |
| `production.deliver` | Marcar "entregue" |
| `production.ticket.cancel` | Cancelar ticket |

### Pagamento / Caixa
| Permissão | O que libera |
|-----------|--------------|
| `payment.create` | Registrar pagamento / gerar cobrança PIX |
| `payment.refund` | Estornar pagamento confirmado |
| `cash.open` | Abrir sessão de caixa |
| `cash.withdraw` | Sangria |
| `cash.deposit` | Suprimento |
| `cash.close` | Fechar caixa |
| `cash.close.view_expected` | Ver valor esperado **antes** de contar (normalmente **negado** a todos — fechamento às cegas) |

### Catálogo / Estoque
| Permissão | O que libera |
|-----------|--------------|
| `catalog.product.manage` | CRUD de produtos, variações, modificadores |
| `catalog.price.manage` | Alterar preços |
| `catalog.station.manage` | Configurar estações e roteamento |
| `stock.movement.create` | Entradas e saídas manuais |
| `stock.adjust` | Ajuste de inventário |
| `stock.recipe.manage` | Ficha técnica |

### Administração / Relatórios
| Permissão | O que libera |
|-----------|--------------|
| `admin.user.manage` | CRUD de usuários e papéis |
| `admin.branch.manage` | CRUD de filiais |
| `admin.capability.manage` | Ligar/desligar capabilities |
| `report.sales.view` | Relatórios de venda, ticket médio, margem |
| `report.audit.view` | Timeline e logs de auditoria |
| `report.production.view` | Métricas de tempo de produção |

---

## 3. Matriz papel × permissão

`●` = concedida · `—` = negada · `▲` = concedida com limite (ver §4)

| Permissão | OWNER | ADMIN | MANAGER | CASHIER | WAITER | KITCHEN | BAR | STOCK | AUDITOR |
|-----------|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| command.open | ● | ● | ● | ● | ● | — | — | — | — |
| command.item.add | ● | ● | ● | ● | ● | — | — | — | — |
| command.item.cancel | ● | ● | ● | ● | ● | — | — | — | — |
| command.item.cancel_in_production | ● | ● | ● | ● | — | ● | ● | — | — |
| command.discount.apply | ● | ● | ● | ▲ | ▲ | — | — | — | — |
| command.discount.authorize | ● | ● | ● | — | — | — | — | — | — |
| command.bill.request | ● | ● | ● | ● | ● | — | — | — | — |
| command.split | ● | ● | ● | ● | ● | — | — | — | — |
| command.close | ● | ● | ● | ● | — | — | — | — | — |
| command.cancel | ● | ● | ● | — | — | — | — | — | — |
| command.reopen | ● | ● | ● | — | — | — | — | — | — |
| order.send | ● | ● | ● | ● | ● | — | — | — | — |
| order.cancel | ● | ● | ● | ● | — | — | — | — | — |
| production.start | ● | ● | ● | — | — | ● | ● | — | — |
| production.ready | ● | ● | ● | — | — | ● | ● | — | — |
| production.deliver | ● | ● | ● | ● | ● | ● | ● | — | — |
| production.ticket.cancel | ● | ● | ● | — | — | ● | ● | — | — |
| payment.create | ● | ● | ● | ● | — | — | — | — | — |
| payment.refund | ● | ● | ● | — | — | — | — | — | — |
| cash.open | ● | ● | ● | ● | — | — | — | — | — |
| cash.withdraw | ● | ● | ● | ● | — | — | — | — | — |
| cash.deposit | ● | ● | ● | ● | — | — | — | — | — |
| cash.close | ● | ● | ● | ● | — | — | — | — | — |
| cash.close.view_expected | ▲ | — | — | — | — | — | — | — | — |
| catalog.product.manage | ● | ● | ● | — | — | — | — | — | — |
| catalog.price.manage | ● | ● | ▲ | — | — | — | — | — | — |
| catalog.station.manage | ● | ● | ● | — | — | — | — | — | — |
| stock.movement.create | ● | ● | ● | — | — | — | — | ● | — |
| stock.adjust | ● | ● | ● | — | — | — | — | ▲ | — |
| stock.recipe.manage | ● | ● | ● | — | — | — | — | ● | — |
| admin.user.manage | ● | ● | — | — | — | — | — | — | — |
| admin.branch.manage | ● | ● | — | — | — | — | — | — | — |
| admin.capability.manage | ● | ▲ | — | — | — | — | — | — | — |
| report.sales.view | ● | ● | ● | ▲ | — | — | — | — | ● |
| report.audit.view | ● | ● | ● | — | — | — | — | — | ● |
| report.production.view | ● | ● | ● | — | — | ● | ● | — | ● |

> `report.sales.view ▲` para CASHIER = só o **fechamento do próprio turno**, não
> o histórico da empresa. `catalog.price.manage ▲` para MANAGER = dentro da
> filial dele. `admin.capability.manage ▲` para ADMIN = não pode desligar
> capability que já tem dado em uso sem confirmação extra.

---

## 4. Autorização de desconto por faixa (seção 32)

Tabela `discount_limits` (`role_code` → `max_percent`):

| Papel | Limite padrão |
|-------|---------------|
| WAITER | 5% |
| CASHIER | 10% |
| MANAGER | 20% |
| ADMIN / OWNER | sem limite (`NULL`) |

**Fluxo quando o desconto pedido ultrapassa a faixa:**

```
Operador pede 15% (é WAITER, limite 5%)
        │
        ▼
Desconto criado com status = PENDING   (NÃO entra no total)
        │  evento DiscountRequested
        ▼
MANAGER/ADMIN recebe notificação → tela "Aprovações"
        │
   ┌────┴─────┐
   ▼          ▼
Aprova     Rejeita
status=APPLIED   status=REJECTED
evento DiscountApplied (authorized_by)   evento DiscountRejected
recalcula total da comanda
        │
        ▼
audit_log SEMPRE (quem pediu, quem autorizou, valor, motivo)
```

Regras:
- O **próprio** valor `PENDING` nunca reduz `total_cents` até virar `APPLIED`.
- Quem autoriza **não pode** ser quem pediu (`authorized_by ≠ requested_by`),
  salvo se tiver limite suficiente para aplicar direto.
- Estorno de pagamento (`payment.refund`) segue a mesma lógica: sempre
  `audit_log`, sempre `MANAGER+`.

---

## 5. UX contextual (seção 44) — o que cada papel VÊ

A permissão controla o que a API aceita; a UI só **esconde** o que não interessa.

| Papel | Menu / telas |
|-------|--------------|
| WAITER | Minhas mesas · Comandas abertas · Lançar pedido · Entregas pendentes |
| KITCHEN / BAR | KDS da estação · Fila · Métricas de tempo (leitura) |
| CASHIER | Comandas a pagar · Pagamentos · Sessão de caixa · Fechamento do turno |
| MANAGER | Tudo de CASHIER/WAITER + Aprovações · Cancelamentos · Fechar caixa · Dashboard da filial |
| ADMIN | Catálogo · Usuários · Estações · Capabilities · Filiais |
| OWNER | Tudo + Dashboard multifilial · Relatórios da empresa |
| AUDITOR | Relatórios · Timeline · Logs (somente leitura) |

---

## 6. Como isso vira código (nota de estudo)

- **Seed** de papéis+permissões por `roles`/`role_permissions` no bootstrap da
  empresa (o "template de negócio" da seção 47 pode variar só os padrões).
- Uma checagem central `PermissionChecker::assert($user, 'command.close', $unitId)`
  (serviço em `Application`), resolvendo as permissões do usuário a partir de
  `user_roles` / `role_permissions`. Sem framework de autorização (ADR-011).
- A checagem acontece no **caso de uso** (`Application`), não no controller nem
  na entidade:
  ```php
  final class CloseCommand {
      public function __invoke(CloseCommandInput $in): void {
          $this->permissions->assert($in->actor, 'command.close', $in->unitId);
          $command = $this->commands->get($in->commandId);
          $command->close();               // a entidade só sabe a regra "saldo = 0"
          $this->commands->save($command);
      }
  }
  ```
- Faixa de desconto: `DiscountPolicy::maxPercentFor($roleCodes)` lê
  `discount_limits` (cache por empresa).

---

## Histórico

| Data | Mudança |
|------|---------|
| 2026-08-29 | Versão inicial. |
| 2026-08-29 | Checagem de permissão sem Gate do Laravel — `PermissionChecker` próprio (ADR-011). |
| 2026-08-30 | Renomeado `Tenant` -> `Company` (tabelas `companies`, `company_capabilities`; coluna `company_id`; `CompanyContext`). O termo "multi-tenant" vira "multiempresa". |
| 2026-08-30 | Renomeado `Unit` -> `Branch` (tabela `branches`, coluna `branch_id`); "unidade" vira "filial" na prosa. `unit`/`unit_price` de medida/preço preservados. |
