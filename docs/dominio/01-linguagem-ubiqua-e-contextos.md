# 01 — Linguagem Ubíqua e Bounded Contexts

> **Linguagem ubíqua**: um único vocabulário usado por código, banco, telas e
> conversa com o cliente. Se a tela diz "comanda", a classe é `Command`, a
> tabela é `commands` e ninguém chama de "conta" no meio do caminho.

---

## 1. Glossário (PT ↔ EN)

| Português | Código (EN) | Definição |
|-----------|-------------|-----------|
| Company / Empresa | `Company` | Cliente da plataforma. Raiz do isolamento de dados. |
| Filial | `Branch` | Ponto de operação de uma empresa (loja, restaurante, salão). Tem mesas, estações e (opcional) estoque próprios. |
| Usuário | `User` | Pessoa que opera o sistema (dono, garçom, caixa, cozinha). |
| Papel | `Role` | Conjunto nomeado de permissões (OWNER, WAITER...). |
| Permissão | `Permission` | Ação atômica autorizável (`command.discount`). |
| Capability | `Capability` | Recurso de plataforma ligado/desligado por empresa (`tables`, `kitchen`). |
| Cliente / Consumidor | `Customer` | Quem consome. Opcional numa comanda de mesa; obrigatório em fiado/fidelidade. |
| Catálogo | `Catalog` | Conjunto de categorias, produtos e serviços de uma filial. |
| Categoria | `Category` | Agrupamento de produtos no cardápio. |
| Produto | `Product` | Item vendável físico/consumível (Chopp, X-Burger). |
| Serviço | `Service` | Item vendável baseado em execução/tempo (Corte de cabelo). *Fase 3.* |
| Variação | `ProductVariant` | Versão de um produto com preço próprio (Chopp 300ml / 500ml). |
| Grupo de modificadores | `ModifierGroup` | Pergunta feita ao pedir (Ponto da carne, Adicionais). |
| Modificador | `Modifier` | Opção dentro do grupo (Bacon +R$5, Sem cebola). |
| Ficha técnica | `Recipe` | Insumos que um produto consome do estoque. |
| Local | `Location` | Ponto físico endereçável por QR (mesa, balcão, quarto, área de evento). |
| Mesa | `Table` | Tipo de `Location` com o vínculo mais comum a uma comanda. |
| Vínculo | `bind_type` | O que a comanda representa: MESA, BALCÃO, CLIENTE, QUARTO, PULSEIRA, EVENTO, VEÍCULO, SERVIÇO, AVULSO. |
| Comanda | `Command` | Acúmulo de consumo de um vínculo, do abrir ao fechar. Controla o total a pagar. |
| Item de comanda | `CommandItem` | Uma linha de consumo na comanda (produto + qtd + modificadores + preço registrado). |
| Pedido | `Order` | Lote de itens lançado de uma vez, com ciclo de vida de produção. Pertence a uma comanda. |
| Item de pedido | `OrderItem` | Item dentro de um pedido, com estado de produção próprio. |
| Canal de entrada | `OrderChannel` | Origem do pedido: TABLE, COUNTER, QR, DELIVERY, WHATSAPP, KIOSK, API. |
| Estação | `Station` | Ponto de produção (COZINHA, BAR, CHAPA, SOBREMESA...). |
| Comanda de produção / Ticket | `ProductionTicket` | Recorte de um pedido roteado para **uma** estação; é o que aparece no KDS. |
| KDS | — | *Kitchen Display System*: tela que lista os `ProductionTicket` de uma estação. |
| Pagamento | `Payment` | Tentativa/registro de quitação de um valor (dinheiro, PIX, cartão...). |
| Forma de pagamento | `PaymentMethod` | Cadastro configurável (Dinheiro, PIX, Crédito Visa...). |
| Caixa (sessão) | `CashRegister` | Sessão de caixa aberta por um operador, com abertura, sangrias/suprimentos e fechamento. |
| Movimento de caixa | `CashMovement` | Entrada/saída na sessão de caixa (venda, sangria, suprimento, troco). |
| Estoque | `Stock` | Saldo de um insumo/produto numa filial. |
| Movimento de estoque | `StockMovement` | Entrada, saída, ajuste ou baixa por venda. |
| Desconto | `Discount` | Redução aplicada a item ou comanda, sujeita a autorização por faixa (seção 32). |
| Divisão de conta | `bill split` | Repartição do total da comanda entre pessoas (igual, por item, por valor, %). |
| Evento de domínio | `DomainEvent` | Fato relevante que aconteceu (`OrderReady`), persistido e publicado. |
| Timeline | — | Sequência de eventos de uma comanda, exibida para auditoria/suporte (seção 55). |
| Log de auditoria | `AuditLog` | Registro de "quem fez o quê e quando" para ações sensíveis. |
| Número amigável | `display_number` | Número curto por filial/dia para humanos (Comanda 42). Não é a PK. |

> **Termos proibidos** (para não gerar sinônimos): "conta" (use *comanda* ou
> *fechamento*), "ticket" isolado (use *pedido* ou *ProductionTicket*), "mesa"
> como sinônimo de comanda (mesa é `Location`; a comanda é o consumo).

---

## 2. Bounded Contexts

Um *bounded context* é uma fronteira dentro da qual um termo tem um significado
único. Entre contextos, a comunicação é por **eventos** ou por **IDs**, nunca
compartilhando tabelas diretamente.

```
┌──────────────────────┐      ┌──────────────────────┐
│  Identidade & Acesso  │      │       Catálogo        │
│  Company, Branch, User,  │      │  Category, Product,   │
│  Role, Permission,    │      │  Variant, Modifier,   │
│  Capability           │      │  Recipe               │
└──────────┬───────────┘      └───────────┬──────────┘
           │ fornece contexto de           │ fornece preço,
           │ company/usuário/permissão       │ estação e ficha técnica
           ▼                                ▼
┌───────────────────────────────────────────────────────┐
│                 Atendimento / Comanda                   │
│   Location, Table, Command, CommandItem, Discount,      │
│   bill split                                            │
└───────┬───────────────────────────────┬───────────────┘
        │ cria pedidos                    │ envia total para quitação
        ▼                                 ▼
┌────────────────────┐          ┌────────────────────────┐
│      Pedidos        │          │  Pagamentos & Caixa    │
│  Order, OrderItem,  │          │  Payment, PaymentMethod,│
│  OrderChannel       │          │  CashRegister,          │
└─────────┬──────────┘          │  CashMovement           │
          │ roteia para estação  └───────────┬────────────┘
          ▼                                   │
┌────────────────────┐                        │ evento PaymentConfirmed
│      Produção       │                        │
│  Station,           │                        ▼
│  ProductionTicket   │          ┌────────────────────────┐
└─────────┬──────────┘          │        Estoque          │
          │ evento OrderDelivered│  Stock, StockMovement   │
          │                      │  (baixa por ficha téc.) │
          └─────────────────────►└────────────────────────┘

        Todos publicam para  ▼
┌───────────────────────────────────────────────────────┐
│              Auditoria & Eventos (transversal)          │
│        DomainEvent (outbox), AuditLog, Timeline         │
└───────────────────────────────────────────────────────┘
```

### 2.1 Identidade & Acesso
Autentica, resolve a empresa do request, diz quais **capabilities** a empresa tem
e se o usuário **pode** executar uma ação. Não sabe o que é uma comanda.

### 2.2 Catálogo
Dono do preço, dos modificadores, da associação **produto → estação** e da
**ficha técnica**. Publica `ProductPriceChanged`. O preço é **copiado** para o
`CommandItem` no momento do lançamento (o histórico não muda se o cardápio mudar).

### 2.3 Atendimento / Comanda
Coração do sistema. Abre/fecha comandas, adiciona itens, aplica desconto, faz
divisão de conta, calcula o total. Cria `Order` quando itens são enviados.
Não sabe fritar nada nem receber dinheiro — delega por evento.

### 2.4 Pedidos
Recebe o lote de itens, define o **canal** e explode o pedido em
`ProductionTicket` por estação. Mantém o estado agregado do pedido.

### 2.5 Produção
Só existe se a capability `kitchen` estiver ligada. Move tickets pela máquina de
estados (recebido → em preparação → pronto). Alimenta o KDS e as métricas de
tempo (seção 15).

### 2.6 Pagamentos & Caixa
Registra pagamentos (com fluxo assíncrono confiável para PIX — seção 23),
controla a sessão de caixa e concilia. Publica `PaymentConfirmed` e
`CommandClosed`.

### 2.7 Estoque
Só com capability `inventory`. Ouve `OrderDelivered`/`CommandClosed`, lê a ficha
técnica e gera `StockMovement` de baixa. Detecta divergência (seção 36) — mas o
alerta em si é Fase 3.

### 2.8 Auditoria & Eventos (transversal)
Não é um domínio de negócio; é infraestrutura de domínio. Persiste todo
`DomainEvent` (padrão *outbox*), monta a **timeline** da comanda e grava
`AuditLog` para ações sensíveis (cancelamento, desconto, reabertura, fechamento
de caixa).

---

## 3. Mapa de relacionamento entre contextos

| De → Para | Tipo de relação | Como se comunicam |
|-----------|-----------------|-------------------|
| Acesso → todos | *Shared Kernel* (só `CompanyId`, `UserId`) + *Conformist* | Middleware injeta contexto; Value Objects compartilhados em `Domain\Shared`. |
| Catálogo → Comanda | *Customer/Supplier* | Comanda consulta preço/estação/modificadores e **copia** para o item. |
| Comanda → Pedidos | mesmo módulo (MVP), agregados distintos | Caso de uso cria `Order` na mesma transação. |
| Pedidos → Produção | *Publisher/Subscriber* | Evento `OrderSentToKitchen` gera os tickets. |
| Produção → Comanda | *Publisher/Subscriber* | `OrderReady`/`OrderDelivered` atualizam a timeline. |
| Comanda → Pagamentos | *Customer/Supplier* | Comanda expõe "valor a pagar"; Pagamentos devolve `PaymentConfirmed`. |
| Pagamentos → Estoque | *Publisher/Subscriber* | `CommandClosed` dispara baixa definitiva/conferência. |
| Todos → Auditoria | *Publisher/Subscriber* | Todo evento cai no outbox; handler monta timeline e audit log. |

---

## 4. Por que essa divisão (nota de estudo)

- **Cada contexto tem um motivo de mudança diferente.** Regras fiscais mudam em
  Pagamentos; layout de cardápio muda em Catálogo. Separados, uma mudança não
  arrasta a outra.
- **Capabilities desligam contextos inteiros.** Uma barbearia sem cozinha
  simplesmente não carrega o contexto Produção — nenhum `if` no core.
- **Eventos entre contextos** deixam o sistema pronto para KDS em tempo real,
  analytics e IA (seções 29, 35–38) sem reengenharia.

---

## Histórico

| Data | Mudança |
|------|---------|
| 2026-08-29 | Versão inicial. |
| 2026-08-30 | Renomeado `Tenant` -> `Company` (tabelas `companies`, `company_capabilities`; coluna `company_id`; `CompanyContext`). O termo "multi-tenant" vira "multiempresa". |
| 2026-08-30 | Renomeado `Unit` -> `Branch` (tabela `branches`, coluna `branch_id`); "unidade" vira "filial" na prosa. `unit`/`unit_price` de medida/preço preservados. |
