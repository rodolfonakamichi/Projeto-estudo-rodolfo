# Documentação de Domínio — Plataforma Universal de Comandas

Esta pasta contém a modelagem que **precede a implementação**, conforme a
seção 61 do documento de pesquisa (`../plataforma-universal-de-comandas.md`).

## Ordem de leitura

| # | Documento | O que responde |
|---|-----------|----------------|
| 00 | [Decisões arquiteturais](00-decisoes-arquiteturais.md) | Stack, multi-tenant, IDs, dinheiro, eventos, o que fica fora do MVP |
| 01 | [Linguagem ubíqua e contextos](01-linguagem-ubiqua-e-contextos.md) | Glossário PT/EN, bounded contexts, mapa de contextos |
| 02 | [Agregados e entidades](02-agregados-e-entidades.md) | Agregados, invariantes, diagrama ER |
| 03 | [Modelo de dados](03-modelo-de-dados.md) | Tabelas MySQL, tipos, índices, chaves |
| 04 | [Máquinas de estado](04-maquinas-de-estado.md) | Comanda, Pedido, Produção, Pagamento, Caixa |
| 05 | [Eventos de domínio](05-eventos-de-dominio.md) | Catálogo de eventos, outbox, idempotência |
| 06 | [Matriz de permissões](06-matriz-de-permissoes.md) | Papéis × permissões, autorização de desconto |
| 07 | [Roadmap do MVP](07-roadmap-mvp.md) | Fatiamento da Fase 1 em entregas verificáveis |

## Status

- [x] 00 — Decisões arquiteturais
- [x] 01 — Linguagem ubíqua e contextos
- [x] 02 — Agregados e entidades
- [x] 03 — Modelo de dados
- [x] 04 — Máquinas de estado
- [x] 05 — Eventos de domínio
- [x] 06 — Matriz de permissões
- [x] 07 — Roadmap do MVP
- [ ] 08 — Estratégia multi-tenant (detalhe de implementação) — *próxima fase*
- [ ] 09 — Estratégia offline/sincronização — *Fase 2*
- [ ] 10 — Especificação da API REST — *antes de codar Presentation*
- [ ] 11–14 — Fluxos de UX (garçom, caixa, cozinha, cliente/QR) — *antes das telas*

## Como usar

Cada decisão relevante vira uma seção. Quando algo mudar, **edite o documento e
registre no histórico** ao final do arquivo. O código deve refletir estes
documentos; divergência é bug de documentação ou de código.
