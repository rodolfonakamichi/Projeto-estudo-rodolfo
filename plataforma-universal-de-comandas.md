# Plataforma Universal de Comandas — Pesquisa, Visão de Produto e Arquitetura

> Documento de referência para concepção de uma plataforma de comandas voltada principalmente para alimentação, mas projetada desde o início para atender outros segmentos que trabalham com consumo, atendimento, serviços ou cobrança.

---

## 1. Visão geral

A principal conclusão da pesquisa é:

> **Não pensar no produto como um “sistema de restaurante”, mas como uma plataforma de gestão de consumo e atendimento.**

O mercado já possui sistemas com:

- Comanda
- PDV
- QR Code
- App do garçom
- KDS
- Autoatendimento
- PIX
- NFC
- Cashless
- Divisão de contas
- Estoque
- Delivery
- Gestão financeira
- Integrações fiscais
- Dashboards

A oportunidade está em criar uma plataforma com um **core genérico**, capaz de atender diferentes tipos de negócios sem duplicar regras de negócio.

A comanda deve ser tratada como uma abstração de:

```text
Consumidor
    ↓
Atendimento
    ↓
Comanda
    ↓
Itens consumidos/serviços
    ↓
Produção ou execução
    ↓
Pagamento
```

---

# 2. Modelo mental do produto

Arquitetura conceitual:

```text
                    CLIENTE
                       │
          ┌────────────┼────────────┐
          ↓            ↓            ↓
       QR Code       Garçom      Balcão/Kiosk
          │            │            │
          └────────────┼────────────┘
                       ↓
                  COMANDA
                       │
             ┌─────────┼─────────┐
             ↓         ↓         ↓
          Cozinha     Bar      Produção
             │         │         │
             └─────────┼─────────┘
                       ↓
                    CAIXA
                       │
             ┌─────────┼─────────┐
             ↓         ↓         ↓
            PIX      Cartão    Dinheiro
                       │
                       ↓
                 Fiscal/Financeiro
```

A ideia central é que diferentes canais possam gerar o mesmo tipo de pedido:

```text
QR
Garçom
WhatsApp
Kiosk
Delivery
API
Balcão
```

Todos devem convergir para:

```text
Order
```

---

# 3. Mercado atual

O mercado de alimentação já possui soluções maduras em:

- PDV
- Mesa/comanda
- QR Code
- App de garçom
- KDS
- Pagamentos
- Estoque
- Delivery
- Gestão financeira
- Integração fiscal

O KDS (Kitchen Display System) vem sendo utilizado para organizar a produção da cozinha e substituir ou complementar impressoras de pedidos.

QR Code também está consolidado, principalmente em cardápios digitais e autoatendimento.

Porém, QR Code não deve ser obrigatório.

O sistema deve permitir:

```text
                    COMANDA
                       │
        ┌──────────────┼──────────────┐
        ↓              ↓              ↓
      QR Code        Garçom         Kiosk
        │              │              │
        └──────────────┼──────────────┘
                       ↓
                    Pedido
```

O cliente pode pedir sozinho ou solicitar atendimento humano.

---

# 4. Principais pontos positivos do conceito

## 4.1 Flexibilidade

Uma mesma comanda pode representar:

- Mesa
- Cliente
- Quarto
- Pulseira
- Evento
- Balcão
- Atendimento
- Veículo
- Serviço
- Consumo avulso

Isso permite atender vários segmentos.

---

## 4.2 Mobile-first

Garçons, vendedores e operadores podem usar:

- Smartphone
- Tablet
- PWA
- Aplicação mobile

A operação não precisa depender de um computador fixo.

---

## 4.3 Redução de etapas

O funcionário precisa conseguir lançar um pedido rapidamente.

Fluxo ideal:

```text
Abrir mesa
   ↓
Produto
   ↓
Quantidade
   ↓
Enviar
```

Evitar fluxos longos:

```text
Abrir mesa
↓
Selecionar cliente
↓
Selecionar categoria
↓
Selecionar produto
↓
Selecionar variação
↓
Selecionar estoque
↓
Confirmar
↓
Selecionar setor
↓
Confirmar novamente
```

---

## 4.4 Centralização

Um único pedido pode passar por:

```text
Atendimento
     ↓
Comanda
     ↓
Produção
     ↓
Caixa
     ↓
Pagamento
     ↓
Estoque
     ↓
Fiscal
     ↓
Financeiro
```

---

## 4.5 Auditoria

Toda alteração importante pode ser registrada.

Exemplo:

```text
19:31 João abriu mesa 12

19:32 João adicionou Coca R$8

19:34 Maria adicionou Hambúrguer R$35

19:35 João cancelou Coca

19:36 Supervisor autorizou desconto

19:50 Caixa fechou comanda
```

Isso ajuda em:

- Suporte
- Segurança
- Controle financeiro
- Prevenção de fraude
- Investigação de divergências

---

# 5. Principais pontos negativos e riscos

## 5.1 Complexidade

Tentar atender todos os segmentos desde o primeiro dia pode transformar o sistema em um conjunto de condicionais:

```php
if ($type === 'restaurant') {
    // ...
}

if ($type === 'bar') {
    // ...
}

if ($type === 'hotel') {
    // ...
}

if ($type === 'beauty') {
    // ...
}
```

Isso deve ser evitado.

A solução é criar um core genérico e módulos específicos.

---

## 5.2 Offline aumenta a complexidade

Operar sem internet exige:

- Persistência local
- Fila de sincronização
- Identificação de dispositivo
- Idempotência
- Controle de conflitos
- Reconciliação
- Eventos

Porém, para restaurantes e eventos, isso pode se tornar um grande diferencial.

---

## 5.3 Muitas funcionalidades podem prejudicar a UX

Adicionar tudo ao mesmo menu pode gerar um sistema difícil de utilizar.

Um garçom não precisa visualizar:

- Financeiro
- Configuração fiscal
- Relatórios administrativos
- Configurações de estoque

A interface deve ser contextual.

---

## 5.4 Dependência de internet

Se o produto depender exclusivamente da nuvem, uma queda de internet pode interromper a operação.

Uma arquitetura híbrida pode ser mais robusta:

```text
                 INTERNET
                    │
                    ↓
                BACKEND
                    │
             ┌──────┴──────┐
             ↓             ↓
           CLOUD        LOCAL
                           │
                    ┌──────┼──────┐
                    ↓      ↓      ↓
                  PDV     KDS   GARÇOM
```

Quando a conexão voltar:

```text
LOCAL
  ↓
SYNC
  ↓
CLOUD
```

---

# 6. Segmentos que podem utilizar

## 6.1 Alimentação

Principal mercado:

- Restaurante
- Bar
- Pub
- Pizzaria
- Hamburgueria
- Lanchonete
- Cafeteria
- Padaria
- Pastelaria
- Sorveteria
- Açaíteria
- Churrascaria
- Food truck
- Self-service
- Delivery
- Dark kitchen

---

## 6.2 Entretenimento

- Balada
- Casa noturna
- Casa de shows
- Eventos
- Festivais
- Camarotes
- Parques
- Espaços de eventos

Possibilidades:

- Pulseiras
- NFC
- Cashless
- Consumação mínima
- Open bar
- Múltiplos bares
- Pré-pagamento

---

## 6.3 Clubes

- Clube social
- Clube esportivo
- Clube de campo
- Clube náutico
- Associações
- Academias com cantina/bar

Exemplo:

```text
Sócio
  ↓
Comanda
  ↓
Consumo
  ↓
Pagamento
```

Também pode existir cobrança posterior na mensalidade.

---

## 6.4 Hotelaria

- Hotel
- Pousada
- Resort
- Hostel

Exemplo:

```text
Quarto 302

Restaurante R$120
Bar R$45
Room service R$80

Total: R$245
```

No checkout:

```text
Hospedagem
+
Consumo
```

O sistema pode futuramente integrar com PMS.

---

## 6.5 Beleza

- Salão
- Barbearia
- Estética
- Manicure
- Clínicas de serviços não médicos
- Spas

Fluxo:

```text
Cliente
 ↓
Agendamento
 ↓
Profissional
 ↓
Serviço
 ↓
Produtos
 ↓
Comanda
 ↓
Pagamento
```

Possibilidades:

- Agenda
- Comissões
- Histórico
- Estoque
- Pacotes
- Serviços
- Produtos

---

# 7. Core universal

A recomendação arquitetural é criar um core baseado em conceitos genéricos.

```text
CORE
│
├── Tenant
├── User
├── Customer
├── Product
├── Service
├── Catalog
├── Order
├── OrderItem
├── Command
├── Payment
├── Inventory
├── Location
└── Audit
```

Depois:

```text
MÓDULOS
│
├── Restaurant
├── Bar
├── Event
├── Hotel
├── Beauty
└── Club
```

O core não deve saber o que é uma pizza.

---

# 8. Comanda

A entidade Comanda pode conter:

```text
Comanda
 ├── identificação
 ├── consumidor
 ├── contexto
 ├── operador
 ├── local
 ├── itens
 ├── status
 ├── pagamentos
 └── histórico
```

Tipos de vínculo:

```text
MESA
BALCÃO
CLIENTE
QUARTO
PULSEIRA
EVENTO
VEÍCULO
SERVIÇO
AVULSO
```

Mesa é apenas um tipo de vínculo.

---

# 9. Produtos e serviços

O cadastro deve suportar produtos e, futuramente, serviços.

Estrutura conceitual:

```text
Produto/Serviço
 ├── variações
 ├── adicionais
 ├── modificadores
 ├── observações
 └── regras
```

## Exemplo — Pizza

```text
Pizza
 → tamanho
 → sabores
 → borda
 → adicionais
```

## Exemplo — Hambúrguer

```text
Hambúrguer
 → pão
 → carne
 → ponto
 → adicionais
```

## Exemplo — Barbearia

```text
Corte
 → profissional
 → duração
```

---

# 10. Adicionais e modificadores

Um dos pontos importantes da UX.

Exemplo:

```text
Hambúrguer

Ponto
 ├── Mal passado
 ├── Ao ponto
 └── Bem passado

Adicionais
 ├── Bacon +R$5
 ├── Cheddar +R$4
 └── Ovo +R$3

Remover
 ├── Cebola
 └── Tomate
```

O usuário deve ser conduzido por uma interface simples, e não por um formulário enorme.

---

# 11. Favoritos e produtos mais usados

Cada operador pode ter:

```text
MAIS VENDIDOS
FAVORITOS
RECENTES
```

Exemplo:

```text
🍺 Chopp
🍔 X-Burger
🍟 Batata
🥤 Coca
🍕 Calabresa
```

O garçom não precisa navegar por dezenas de categorias.

---

# 12. Busca rápida

Exemplo:

```text
⌕ "chopp"

Chopp 300ml
Chopp 500ml
Chopp IPA
```

Outro exemplo:

```text
⌕ "coca"

Coca-Cola
Coca Zero
Coca 1L
```

---

# 13. KDS — Kitchen Display System

O KDS organiza os pedidos por estação.

```text
             PEDIDO
                ↓
       ┌────────┴────────┐
       ↓                 ↓
     COZINHA             BAR
       ↓                 ↓
    Hambúrguer          Chopp
    Batata              Caipirinha
```

Estações podem ser configuráveis:

```text
COZINHA
BAR
CAFÉ
SUSHI
PIZZA
SOBREMESA
EXPEDIÇÃO
CAIXA
```

Cada produto pode ser associado a uma estação.

---

# 14. Máquina de estados do pedido

Sugestão:

```text
CRIADO
   ↓
ENVIADO
   ↓
RECEBIDO
   ↓
EM_PREPARACAO
   ↓
PRONTO
   ↓
ENTREGUE
```

Estados excepcionais:

```text
CANCELADO
PAUSADO
ERRO
```

Isso permite medir o tempo de produção.

---

# 15. Métricas de produção

Exemplo:

```text
Pedido #9321

Recebido:  19:31
Iniciado:  19:34
Pronto:    19:42

Tempo:     11 minutos
```

Depois:

```text
Hambúrguer
Tempo médio: 8m32s

Pizza
Tempo médio: 14m21s

Chopp
Tempo médio: 42s
```

O sistema deixa de apenas registrar vendas e passa a fornecer inteligência operacional.

---

# 16. Alertas inteligentes

Exemplo:

```text
⚠️ Cozinha

Pedido #9381
14 minutos aguardando

Tempo médio: 8 minutos
```

Outro:

```text
🔴 ATENÇÃO

5 pedidos atrasados
```

---

# 17. QR Code

O QR Code pode oferecer:

```text
[ PEDIR ]
[ CHAMAR GARÇOM ]
[ PEDIR CONTA ]
[ VER CONTA ]
```

Solicitações ao garçom:

```text
○ Quero fazer pedido
○ Quero pagar
○ Preciso de ajuda
○ Quero água
○ Quero outro cardápio
```

O funcionário recebe:

```text
Mesa 17

🔔 Solicitação
"Quero pagar"
```

---

# 18. QR Code como canal, não como domínio

É importante não modelar o sistema pensando:

```text
QR = Comanda
```

O QR deve representar uma localização ou ponto de atendimento.

Exemplo:

```text
QR físico
    ↓
Location
    ↓
Tenant
    ↓
Unidade
    ↓
Mesa
```

Assim o mesmo mecanismo pode representar:

- Mesa
- Quarto
- Pulseira
- Evento
- Balcão

---

# 19. QR Code permanente

Exemplo:

```text
Mesa 12
QR-12
```

O QR físico permanece.

Ao escanear:

```text
QR-12
   ↓
Tenant
   ↓
Unidade
   ↓
Mesa 12
   ↓
Cardápio
```

O backend pode criar ou localizar a comanda automaticamente.

---

# 20. Autoatendimento

Fluxo:

```text
QR
 ↓
Cardápio
 ↓
Pedido
 ↓
Pagamento
 ↓
Produção
```

Porém, o autoatendimento deve coexistir com atendimento humano.

A proposta não precisa ser:

> "Substituir o garçom."

Uma proposta melhor:

> **"Seu garçom perde menos tempo digitando e mais tempo atendendo."**

---

# 21. Divisão de conta

Exemplo:

```text
Mesa 20

João
 ├── Chopp
 └── Hambúrguer

Maria
 ├── Coca
 └── Pizza

Pedro
 └── Cerveja
```

Fechamento:

```text
João     R$58
Maria    R$42
Pedro    R$18
```

---

# 22. Formas de dividir

## Igualmente

```text
R$200 / 4
= R$50
```

## Por item

```text
João
Hambúrguer
Chopp
```

## Por pessoa

Cada pessoa assume seus itens.

## Por valor

```text
João → R$50
Maria → R$80
```

## Por percentual

```text
João → 30%
Maria → 70%
```

---

# 23. Pagamentos

Formas:

```text
Dinheiro
Débito
Crédito
PIX
Voucher
Outros
```

PIX deve seguir um fluxo confiável:

```text
Gerar cobrança
       ↓
QR Code
       ↓
Aguardar confirmação
       ↓
Webhook
       ↓
Pagamento confirmado
       ↓
Comanda paga
```

Não confiar somente em uma ação do cliente informando que pagou.

---

# 24. Cashless e NFC

Especialmente interessante para:

- Eventos
- Festivais
- Baladas
- Clubes
- Parques
- Resorts

Exemplo:

```text
Pulseira #A812

Saldo:
R$120
```

Consumo:

```text
Aproxima
   ↓
Cliente identificado
   ↓
Pedido
   ↓
Saldo atualizado
```

---

# 25. Comanda pré-paga

```text
Cliente coloca R$100
        ↓
Recebe QR/NFC
        ↓
Consome
        ↓
Saldo diminui
```

---

# 26. Comanda pós-paga

```text
Cliente recebe pulseira
        ↓
Consome
        ↓
Tudo é registrado
        ↓
Paga na saída
```

---

# 27. Offline

Uma das funcionalidades com maior potencial de diferenciação.

Se a internet cair:

```text
Operação continua
```

Arquitetura:

```text
Dispositivo
   ↓
Banco local
   ↓
Fila de eventos
   ↓
Sincronização
   ↓
Backend
```

---

# 28. Sincronização

Problema:

```text
Garçom A:
Mesa 10 → +Coca

Garçom B:
Mesa 10 → +Cerveja
```

Os dois estão offline.

Depois sincronizam.

Não é seguro simplesmente sobrescrever o estado:

```sql
UPDATE command SET ...
```

Uma abordagem mais robusta é registrar operações/eventos:

```json
{
    "event_id": "uuid",
    "type": "ITEM_ADDED",
    "aggregate_id": "comanda-123",
    "payload": {
        "product_id": 10,
        "quantity": 2
    },
    "created_at": "...",
    "device_id": "tablet-07"
}
```

Isso facilita:

- Sincronização
- Auditoria
- Idempotência
- Recuperação
- Diagnóstico

---

# 29. Arquitetura orientada a eventos

Eventos sugeridos:

```text
OrderCreated
OrderItemAdded
OrderItemRemoved
OrderSentToKitchen
OrderStarted
OrderReady
OrderDelivered
OrderCancelled
PaymentCreated
PaymentConfirmed
CommandClosed
```

Esses eventos podem alimentar:

```text
KDS
Notificações
Auditoria
Analytics
Estoque
Relatórios
Integrações
```

---

# 30. Auditoria

A timeline da comanda pode ser:

```text
COMANDA #92831

19:02 Aberta
19:03 Coca adicionada
19:04 Hambúrguer adicionado
19:04 Pedido enviado
19:05 Cozinha iniciou
19:12 Hambúrguer pronto
19:13 Entregue
19:30 Chopp adicionado
19:31 Pedido enviado
19:32 Chopp entregue
19:40 Pagamento PIX
19:40 Comanda fechada
```

Isso é extremamente útil para suporte e gestão.

---

# 31. Usuários e permissões

Papéis:

```text
OWNER
ADMIN
MANAGER
CASHIER
WAITER
KITCHEN
BAR
STOCK
AUDITOR
```

Permissões:

```text
comanda.create
comanda.update
comanda.cancel
comanda.discount

payment.create
payment.refund

product.update
stock.update

report.view
```

---

# 32. Descontos com autorização

Exemplo:

```text
Garçom:
até 5%

Gerente:
até 20%

Administrador:
sem limite
```

Quando ultrapassar:

```text
Solicitar autorização
```

Isso permite controle e rastreabilidade.

---

# 33. Estoque

A venda deve gerar movimentação de estoque.

Fluxo:

```text
Venda
 ↓
Item vendido
 ↓
Ficha técnica
 ↓
Movimentação de estoque
```

---

# 34. Ficha técnica

Exemplo:

```text
X-Burger

Pão       1 unidade
Carne     150g
Queijo    1 fatia
Molho     20g
```

Se 100 hambúrgueres forem vendidos:

```text
100 pães
15kg carne
100 queijos
2kg molho
```

Isso permite calcular:

- Custo
- Margem
- Desperdício
- Estoque
- Consumo esperado

---

# 35. Inteligência e IA

A IA deve ser aplicada onde existe valor operacional.

## Previsão de demanda

```text
Sexta-feira
19h-21h

Previsão:
320 pedidos
```

Sugestão:

```text
Preparar:
+30% carne
+20% pão
+15% batata
```

---

# 36. Detecção de desperdício

Exemplo:

```text
Vendas:
100 pizzas

Consumo esperado:
10kg queijo

Estoque baixou:
14kg
```

Sistema:

```text
⚠️ Consumo de queijo 40% acima do esperado.
```

Possíveis causas:

- Desperdício
- Ficha técnica incorreta
- Porcionamento
- Cadastro errado
- Fraude

---

# 37. IA para análise de cardápio

Exemplo:

```text
Produto:
X-Bacon

Vendas:
↑ 32%

Margem:
Alta

Sugestão:
Destacar no cardápio
```

Outro:

```text
Produto:
Suco de manga

Vendas:
↓ 41%

Margem:
Baixa

Sugestão:
Revisar preço ou posição
```

---

# 38. IA para gargalos

Exemplo:

```text
Pedidos entre 19h e 21h

Tempo médio:
12min

Normal:
7min
```

Sistema:

```text
⚠️ Gargalo recorrente na estação COZINHA entre 19h e 21h.
```

---

# 39. Delivery

Não precisa necessariamente estar no MVP, mas deve ser previsto arquiteturalmente.

```text
                PEDIDOS
                   │
       ┌───────────┼───────────┐
       ↓           ↓           ↓
      Mesa       Balcão     Delivery
       │           │           │
       └───────────┼───────────┘
                   ↓
                COMANDA
                   ↓
                PRODUÇÃO
```

Todos os canais devem utilizar o mesmo domínio de pedidos.

---

# 40. WhatsApp

Pode futuramente funcionar como canal de venda:

```text
Cliente
   ↓
WhatsApp
   ↓
Bot
   ↓
Cardápio
   ↓
Pedido
   ↓
Order
```

O WhatsApp não deve criar uma estrutura de pedido diferente.

Ele deve gerar o mesmo:

```text
Order
```

---

# 41. Canais de entrada

Modelo:

```text
OrderChannel

TABLE
COUNTER
QR
DELIVERY
WHATSAPP
KIOSK
API
```

Assim:

```text
QR
Garçom
WhatsApp
Kiosk
Delivery
API
```

todos produzem:

```text
Order
```

Isso evita duplicação.

---

# 42. Multiempresa / Multiunidade

Estrutura:

```text
Tenant
   │
   ├── Unidade A
   │      ├── mesas
   │      ├── produtos
   │      └── estoque
   │
   ├── Unidade B
   │      ├── mesas
   │      ├── produtos
   │      └── estoque
   │
   └── Unidade C
```

Dashboard:

```text
Unidade A
R$18.320

Unidade B
R$12.540

Unidade C
R$21.890
```

---

# 43. Dashboard

Evitar excesso de gráficos.

Primeiro nível:

```text
Faturamento
R$18.920

Pedidos
382

Ticket médio
R$49,52

Margem
32%
```

Depois:

```text
Produtos mais vendidos

1. X-Burger
2. Coca
3. Batata
4. Chopp
```

E alertas:

```text
⚠️ 3 alertas

• 4 pedidos atrasados
• estoque baixo de queijo
• caixa divergente R$82
```

---

# 44. UX contextual

Se o usuário é garçom:

```text
Minhas mesas
Pedidos
Comandas
```

Se é cozinha:

```text
Pedidos
Tempo
Produção
```

Se é caixa:

```text
Comandas
Pagamentos
Caixa
```

Se é proprietário:

```text
Dashboard
Vendas
Estoque
Relatórios
```

O usuário não deve visualizar funções que não fazem parte da operação dele.

---

# 45. Favoritos e atalhos

A interface do garçom deve privilegiar:

```text
MAIS VENDIDOS
FAVORITOS
RECENTES
BUSCA
```

O objetivo é reduzir o tempo entre:

```text
Cliente pediu
        ↓
Garçom registrou
```

---

# 46. Onboarding

Um wizard inicial pode ser:

```text
1. Tipo de negócio
        ↓
2. Nome
        ↓
3. Produtos
        ↓
4. Mesas/estações
        ↓
5. Formas de pagamento
        ↓
6. Usuários
        ↓
7. Pronto
```

Tipos:

```text
🍔 Restaurante
🍺 Bar
🍕 Pizzaria
💈 Barbearia
🎪 Evento
🏨 Hotel
⚙️ Outro
```

---

# 47. Templates de negócio

Selecionando o segmento, o sistema pode configurar recursos automaticamente.

## Restaurante

```text
Mesas
Garçom
Cozinha
Bar
Comanda
KDS
Caixa
```

## Pizzaria

```text
Mesas
Delivery
Sabores
Bordas
Tamanhos
Forno
```

## Barbearia

```text
Agenda
Profissionais
Comanda
Comissões
Produtos
```

## Evento

```text
Entrada
Pulseiras
Cashless
PDVs
Consumo
```

---

# 48. Modelo de Capability

Em vez de amarrar o sistema ao tipo do estabelecimento:

```text
Tenant
 ├── capability.tables
 ├── capability.kitchen
 ├── capability.delivery
 ├── capability.appointment
 ├── capability.cashless
 ├── capability.hotel
 ├── capability.inventory
 └── capability.commission
```

Exemplo:

## Restaurante

```text
tables
kitchen
delivery
inventory
```

## Barbearia

```text
appointment
commission
inventory
```

## Balada

```text
cashless
event
bar
```

Isso deixa a plataforma modular.

---

# 49. Modo de operação

Interfaces específicas:

```text
MODO ATENDIMENTO
        ↓
MODO PRODUÇÃO
        ↓
MODO CAIXA
        ↓
MODO GESTÃO
        ↓
MODO CLIENTE
```

Todos podem consumir o mesmo backend.

---

# 50. MVP recomendado

Não implementar tudo de uma vez.

Primeiro:

```text
                    MVP
                     │
        ┌────────────┼────────────┐
        ↓            ↓            ↓
     CADASTRO      COMANDA      PEDIDO
        │            │            │
        ↓            ↓            ↓
    Produtos       Mesa        KDS
                                  │
                                  ↓
                               CAIXA
                                  │
                                  ↓
                              PAGAMENTO
```

## Módulos do MVP

1. Empresa
2. Usuários
3. Produtos
4. Categorias
5. Mesas
6. Comandas
7. Pedidos
8. KDS básico
9. Caixa
10. Pagamentos
11. Relatórios
12. Auditoria

Interfaces:

```text
Web Admin
PWA Garçom
PWA KDS
QR Code
```

---

# 51. Roadmap

## Fase 1 — Motor de comanda

```text
Tenant
Usuário
Produto
Cliente
Local
Mesa
Comanda
Pedido
Item
Pagamento
Caixa
Auditoria
```

Prioridade: criar um núcleo extremamente sólido.

---

## Fase 2 — Operação

```text
PWA Garçom
KDS
QR Code
PIX
Divisão de conta
Offline
Estoque
Dashboard
```

---

## Fase 3 — Plataforma

```text
Delivery
WhatsApp
NFC
Cashless
Agenda
Hotel
Clube
Eventos
Fidelidade
IA
API pública
Marketplace de integrações
```

---

# 52. Arquitetura PHP sugerida

Uma arquitetura orientada ao domínio pode ser organizada assim:

```text
src/
├── Domain/
│   ├── Tenant/
│   ├── User/
│   ├── Customer/
│   ├── Catalog/
│   ├── Order/
│   ├── Command/
│   ├── Payment/
│   ├── Inventory/
│   ├── Table/
│   └── Audit/
│
├── Application/
│   ├── Command/
│   ├── Order/
│   ├── Payment/
│   └── ...
│
├── Infrastructure/
│   ├── Persistence/
│   ├── Queue/
│   ├── Cache/
│   ├── Payment/
│   └── Notification/
│
└── Presentation/
    ├── Http/
    ├── Websocket/
    └── API/
```

O conceito é separar:

```text
Domain
    ↓
Regras de negócio

Application
    ↓
Casos de uso

Infrastructure
    ↓
Banco, cache, filas, APIs externas

Presentation
    ↓
HTTP, WebSocket, API, interfaces
```

---

# 53. Possíveis entidades iniciais

```text
Tenant
Unit
User
Role
Permission

Customer

Catalog
Category
Product
ProductVariant
ModifierGroup
Modifier
Service

Location
Table

Command
CommandItem

Order
OrderItem
OrderChannel

Station
ProductionOrder

Payment
PaymentMethod
CashRegister
CashMovement

Inventory
Stock
StockMovement
Recipe
RecipeItem

AuditLog
Event
```

---

# 54. Fluxo completo

```text
Cliente
   ↓
Canal de entrada
   │
   ├── QR
   ├── Garçom
   ├── Balcão
   ├── Kiosk
   ├── WhatsApp
   ├── Delivery
   └── API
   ↓
Order
   ↓
Command
   ↓
OrderItem
   ↓
Production
   ↓
Station
   ↓
KDS
   ↓
Entrega
   ↓
Pagamento
   ↓
Caixa
   ↓
Estoque
   ↓
Fiscal
   ↓
Financeiro
   ↓
Analytics
```

---

# 55. Timeline como recurso central

A comanda pode ser entendida como uma sequência de eventos.

```text
19:02 → Comanda aberta
19:03 → Item adicionado
19:04 → Pedido enviado
19:05 → Produção iniciada
19:12 → Item pronto
19:13 → Entregue
19:30 → Novo item
19:31 → Pedido enviado
19:32 → Entregue
19:40 → Pagamento confirmado
19:40 → Comanda encerrada
```

Isso pode alimentar:

- Auditoria
- Métricas
- KDS
- Relatórios
- Suporte
- Analytics
- IA

---

# 56. Principais funcionalidades e prioridade

| Funcionalidade | Prioridade |
|---|---:|
| Comanda | 🔥🔥🔥 |
| Produtos | 🔥🔥🔥 |
| Pedido | 🔥🔥🔥 |
| Pagamento | 🔥🔥🔥 |
| Caixa | 🔥🔥🔥 |
| Mesas | 🔥🔥🔥 |
| Usuários/permissões | 🔥🔥🔥 |
| Auditoria | 🔥🔥🔥 |
| App garçom | 🔥🔥🔥 |
| KDS | 🔥🔥 |
| QR Code | 🔥🔥 |
| PIX | 🔥🔥🔥 |
| Estoque | 🔥🔥 |
| Divisão de conta | 🔥🔥 |
| Offline | 🔥🔥🔥 |
| Dashboard | 🔥🔥 |
| Delivery | 🔥🔥 |
| WhatsApp | 🔥 |
| NFC | 🔥 |
| Agenda | 🔥 |
| Hotel/PMS | Futuro |
| IA | Futuro |
| Fidelidade | Futuro |
| Autoatendimento | Futuro |

---

# 57. Estratégia de diferenciação

Não competir apenas por quantidade de funcionalidades.

Os principais diferenciais deveriam ser:

## 1. Velocidade

Pedido em poucos toques.

## 2. Simplicidade

Funcionário aprende rapidamente.

## 3. Flexibilidade

Mesa, cliente, quarto, pulseira, evento etc.

## 4. Confiabilidade

Continua funcionando mesmo com problemas de conexão.

## 5. Integração

```text
QR
Garçom
KDS
Caixa
PIX
Estoque
Fiscal
Delivery
```

## 6. Inteligência

O sistema não apenas registra o que aconteceu.

Ele ajuda a explicar:

```text
Por que as vendas caíram?
Onde está o gargalo?
Qual produto tem melhor margem?
Onde existe desperdício?
Quando a operação ficará sobrecarregada?
```

---

# 58. Posicionamento sugerido

Em vez de:

> Sistema de comandas para restaurantes.

Posicionamento mais abrangente:

> **Plataforma universal de atendimento, consumo e operação.**

Ou:

> **Uma única plataforma para vender, atender, produzir e receber.**

A comanda passa a ser o elemento central que conecta essas operações.

---

# 59. Conclusão

O mercado de comandas já possui soluções consolidadas. Portanto, criar apenas:

```text
Mesa
+
Produto
+
Comanda
+
Pagamento
```

não seria suficiente para gerar uma diferenciação forte.

A oportunidade está em criar uma plataforma com:

```text
CORE UNIVERSAL
       +
CANAIS DE VENDA
       +
COMANDA
       +
PRODUÇÃO
       +
PAGAMENTO
       +
ESTOQUE
       +
AUDITORIA
       +
OFFLINE
       +
ANALYTICS
       +
IA
```

A visão de longo prazo é transformar:

> **“Sistema de comanda”**

em:

> **“Plataforma de gestão de consumo e atendimento.”**

Isso permite começar fortemente em alimentação, onde existe maior volume de potenciais clientes, e posteriormente expandir para bares, eventos, clubes, hotéis, salões, barbearias e outros estabelecimentos.

---

# 60. Diretriz arquitetural principal

A decisão mais importante para o projeto é:

> **O core não deve conhecer o segmento do cliente.**

Evitar:

```php
if ($businessType === 'restaurant') {
    // regra
}
```

Preferir:

```text
Capabilities
Modules
Strategies
Policies
Events
```

Exemplo:

```text
Tenant
 ├── tables
 ├── kitchen
 ├── delivery
 ├── inventory
 └── cashless
```

Dessa forma, o mesmo motor pode atender diferentes negócios.

---

# 61. Próximo passo recomendado

Antes de começar o desenvolvimento, elaborar:

1. **Mapa completo do domínio**
2. **Diagrama de entidades**
3. **Modelo de dados**
4. **Estados da comanda**
5. **Estados do pedido**
6. **Eventos do sistema**
7. **Matriz de permissões**
8. **Arquitetura multi-tenant**
9. **Estratégia offline/sincronização**
10. **API**
11. **Fluxos UX do garçom**
12. **Fluxos UX do caixa**
13. **Fluxos UX da cozinha**
14. **Fluxo do cliente via QR**
15. **Roadmap do MVP**

A recomendação é fazer essa etapa antes de implementar as telas, porque as decisões do domínio — principalmente `Order`, `Command`, `Location`, `Product`, `Payment`, `Station` e `Capability` — vão determinar se o sistema conseguirá realmente ser universal ou se ficará limitado a restaurantes.
