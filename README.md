# Projeto-estudo-rodolfo — Plataforma Universal de Comandas

Projeto de estudo: construir, **passo a passo**, uma plataforma de comandas que
começa em alimentação e é projetada para atender bares, eventos, clubes, hotéis
e salões sem reescrever o núcleo.

**Status atual:** modelagem de domínio concluída. Sem código de aplicação ainda.

---

## Modo de leitura

Leia **nesta ordem** — cada documento assume o anterior.

### 1. Pesquisa de mercado e visão de produto
[`docs/plataforma-universal-de-comandas.md`](docs/plataforma-universal-de-comandas.md)

O "porquê" do projeto: o que o mercado já tem, onde está a oportunidade, os
segmentos-alvo, os riscos e o MVP recomendado. É a fonte de tudo que vem depois.
Se tiver pouco tempo, leia as seções **1, 2, 7, 8, 48, 50, 51, 60 e 61**.

### 2. Modelagem de domínio
[`docs/dominio/`](docs/dominio/README.md) — comece pelo `README.md` da pasta.

Traduz a pesquisa em decisões concretas, na ordem que a seção 61 da pesquisa
recomenda:

| # | Documento | Responde |
|---|-----------|----------|
| 00 | Decisões arquiteturais | Stack, camadas, IDs, dinheiro, multi-tenant, o que fica fora do MVP |
| 01 | Linguagem ubíqua e contextos | Vocabulário PT/EN e as fronteiras do sistema |
| 02 | Agregados e entidades | Quais objetos existem e quais regras eles protegem |
| 03 | Modelo de dados | Tabelas MySQL, tipos, índices |
| 04 | Máquinas de estado | Ciclo de vida de comanda, pedido, produção, pagamento, caixa |
| 05 | Eventos de domínio | O que é registrado quando algo acontece |
| 06 | Matriz de permissões | Quem pode fazer o quê |
| 07 | Roadmap do MVP | Fase 1 fatiada em entregas (M0–M11) com critério de aceite |

### 3. Implementação
Ainda não iniciada. O ponto de partida é o milestone **M0** do documento 07.

---

## Stack

PHP 8.3 **sem framework** · Composer (PSR-4) · MySQL 8 (PDO) · Phinx (migrations)
· PHPUnit · (KDS em tempo real: SSE no MVP)

Arquitetura em camadas montada à mão:
`Domain` (regra pura) → `Application` (casos de uso) → `Infrastructure`
(banco via PDO, gateways) → `Presentation` (HTTP, API, WebSocket/SSE).
Detalhes nas ADR-002 e ADR-011 (documento 00).

---

## Como contribuir com a modelagem

Os documentos de `docs/dominio/` são a fonte da verdade. Se algo mudar:
editar o documento e registrar na tabela **Histórico** ao final do arquivo.
Divergência entre documento e código é tratada como bug.

---

## Branches

- `main` — base estável
- `minha-feature` — trabalho em andamento
