# Projeto-estudo-rodolfo — Plataforma Universal de Comandas

Projeto de estudo: construir, **passo a passo**, uma plataforma de comandas que
começa em alimentação e é projetada para atender bares, eventos, clubes, hotéis
e salões sem reescrever o núcleo.

**Status atual:** modelagem de domínio concluída. **M0 (fundação) refeito sobre
Symfony 7.4 LTS** (ADR-012) — Docker, Doctrine, Messenger, CI com PHPStan/Deptrac.
Próximo: M1 (identidade e multiempresa). Ver documento 07 e `PROGRESSO.md`.

---

## Modo de leitura

Leia **nesta ordem** — cada documento assume o anterior.

### 1. Pesquisa de mercado e visão de produto
[`docs/plataforma-universal-de-comandas.md`](docs/plataforma-universal-de-comandas.md)

O "porquê" do projeto: o que o mercado já tem, onde está a oportfilial, os
segmentos-alvo, os riscos e o MVP recomendado. É a fonte de tudo que vem depois.
Se tiver pouco tempo, leia as seções **1, 2, 7, 8, 48, 50, 51, 60 e 61**.

### 2. Modelagem de domínio
[`docs/dominio/`](docs/dominio/README.md) — comece pelo `README.md` da pasta.

Traduz a pesquisa em decisões concretas, na ordem que a seção 61 da pesquisa
recomenda:

| # | Documento | Responde |
|---|-----------|----------|
| 00 | Decisões arquiteturais | Stack, camadas, IDs, dinheiro, multiempresa, o que fica fora do MVP |
| 01 | Linguagem ubíqua e contextos | Vocabulário PT/EN e as fronteiras do sistema |
| 02 | Agregados e entidades | Quais objetos existem e quais regras eles protegem |
| 03 | Modelo de dados | Tabelas MySQL, tipos, índices |
| 04 | Máquinas de estado | Ciclo de vida de comanda, pedido, produção, pagamento, caixa |
| 05 | Eventos de domínio | O que é registrado quando algo acontece |
| 06 | Matriz de permissões | Quem pode fazer o quê |
| 07 | Roadmap do MVP | Fase 1 fatiada em entregas (M0–M11) com critério de aceite |

### 3. Implementação
Em andamento no milestone **M1**. Convenções de código em
[`docs/CONVENCOES.md`](docs/CONVENCOES.md).

---

## Stack

PHP 8.3 · **Symfony 7.4 LTS** · Doctrine ORM + Migrations · Messenger · MySQL 8.4
· PHPUnit · PHPStan (nível 8) · php-cs-fixer · Deptrac · (KDS em tempo real: SSE no MVP)

Arquitetura hexagonal em camadas, SOLID e padrões de projeto (tabela na ADR-012):
`Domain` (regra pura, sem framework) ← `Application` (casos de uso: Command +
Handler) ← `Presentation` (controllers, comandos CLI); `Infrastructure`
(Doctrine, Messenger, gateways) implementa as interfaces do Domain/Application.
Detalhes nas ADR-002 e ADR-012 (documento 00).

---

## Desenvolvimento

Tudo roda em Docker isolado deste projeto (ver [`docker/README.md`](docker/README.md)).
Nada é instalado no host.

### Subir / derrubar o ambiente

```bash
cp .env.example .env                       # 1ª vez
docker compose build php                   # 1ª vez, ou quando o Dockerfile mudar
docker compose run --rm php composer install
docker compose up -d                       # sobe php + nginx + db
docker compose exec php bin/console doctrine:migrations:migrate   # aplica o schema
docker compose down                        # derruba (mantém o banco)
docker compose down -v                     # derruba e apaga o banco
```

App em `http://localhost:8080` · MySQL do host em `127.0.0.1:33061`.

### Atalho: rodar comandos no container

```bash
docker compose exec php <comando>          # roda dentro do container PHP
```

### Console do Symfony

```bash
docker compose exec php bin/console                        # lista tudo
docker compose exec php bin/console app:health             # PHP + MySQL respondem?
docker compose exec php bin/console debug:router           # rotas
docker compose exec php bin/console make:migration         # gera migration a partir do mapeamento
docker compose exec php bin/console doctrine:migrations:migrate
docker compose exec php bin/console make:test              # esqueleto de teste (Maker)
```

### Testes (PHPUnit)

```bash
docker compose exec php bin/phpunit
docker compose exec php bin/phpunit --filter test_allocate
docker compose exec php bin/phpunit --testdox      # saída legível
```

### Qualidade

```bash
docker compose exec php composer cs                       # formata (php-cs-fixer)
docker compose exec php vendor/bin/phpstan analyse        # análise estática
docker compose exec php vendor/bin/deptrac analyse        # regra de camadas
```

### Dependências

```bash
docker compose exec php composer require <pacote>          # produção
docker compose exec php composer require --dev <pacote>    # dev/CI
docker compose exec php composer install                   # após git pull
```

### Antes de commitar

```bash
docker compose exec php composer cs       # formata
docker compose exec php composer check    # tudo que o CI roda
```

---

## Como contribuir com a modelagem

Os documentos de `docs/dominio/` são a fonte da verdade. Se algo mudar:
editar o documento e registrar na tabela **Histórico** ao final do arquivo.
Divergência entre documento e código é tratada como bug.

---

## Branches

Um branch por milestone (`m1-...`), merge na `main` quando o aceite passa.
