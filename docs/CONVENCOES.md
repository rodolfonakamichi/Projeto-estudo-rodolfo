# Convenções do projeto

Regras práticas que valem para todo o código e documentação. O que é decisão
arquitetural com contexto/consequência fica nas ADRs (`docs/dominio/00-...`).

## Documentação

- **Um `README.md` por diretório de topo**, não por subpasta:
  `docker/README.md`, `src/README.md`, `tests/README.md`, `docs/dominio/README.md`.
  O README cobre tudo abaixo dele. Evita ter mais README do que código.
- Decisão arquitetural com contexto/consequência → ADR em `docs/dominio/00-...`.
  Regra prática curta → este arquivo.

## Arquivos

- **Encoding: UTF-8** (sem BOM), em **todos** os arquivos. Sem ISO-8859-1.
  Motivos: `json_encode()` só aceita UTF-8 e a API é JSON; o MySQL está em
  `utf8mb4`; mistura de encoding gera mojibake. Forçado pelo `.editorconfig`;
  verificado no CI (M0.7).
- **Fim de linha: LF** (`\n`), nunca CRLF.
- **Sempre** newline no final do arquivo.
- Sem espaço em branco no fim das linhas (exceto `.md`, onde dois espaços têm
  significado).

## PHP

- `declare(strict_types=1);` no topo de **todo** arquivo `.php`.
- `namespace` PSR-4: caminho do arquivo = namespace. `App\Domain\Shared\Money`
  → `src/Domain/Shared/Money.php`. Sensível a maiúsculas.
- Formatação: **php-cs-fixer**, regras `@Symfony` (config em
  `.php-cs-fixer.dist.php`). Não formatar na mão; rodar `composer cs`.
- Análise estática: **PHPStan** nível 8 deve passar sem erro.
- Camadas: **Deptrac** (`deptrac.yaml`) deve passar sem violação.
- Framework só fora do `Domain`: nada de `#[ORM\...]`, `Request` ou serviço do
  Symfony em `src/Domain` (ADR-002/012).
- Gerar esqueleto com o **Maker** (`make:command`, `make:test`,
  `make:migration`...) e então mover/ajustar para a camada certa.
- `final` por padrão em classes (VOs, casos de uso, serviços).
  Abrir para herança só com motivo. **Exceção: entidades mapeadas pelo
  Doctrine** não são `final` — no PHP 8.3 o lazy loading gera um *proxy* que
  estende a classe (no PHP 8.4 os *lazy objects* nativos resolvem isso).
  Pelo mesmo motivo, a propriedade de **identidade** (`$id`, ou as partes de
  uma PK composta) não é `readonly`: o proxy já nasce com o id e o Doctrine o
  regrava ao inicializar. Fica `private` e sem setter.
- Value Objects: imutáveis (`readonly`), construtor privado + *named
  constructors* (`fromCents`, `fromString`), igualdade por valor (`equals`).

## Testes

- **PHPUnit**. Arquivo espelha `src/` em `tests/`, namespace `App\Tests\`.
- `Domain`: `TestCase` puro, sem kernel. HTTP: `WebTestCase`.
- Nome do teste descreve o comportamento: `test_soma_dois_valores_da_mesma_moeda`.
- `assertSame` (tipo + valor) em vez de `assertEquals` sempre que possível.
- TDD quando fizer sentido: teste vermelho → código verde → refatora.

## Indentação

- PHP, Dockerfile: 4 espaços.
- YAML, JSON: 2 espaços.
- Nunca tab.

## Nomes

- Classes: `PascalCase`. Métodos e variáveis: `camelCase`.
- Tabelas e colunas: `snake_case`, plural nas tabelas (`command_items`).
- Eventos de domínio: `PascalCase` no particípio (`OrderItemAdded`).
- Idioma: **português** em documentação, comentários, nomes de teste e mensagens
  de domínio voltadas ao usuário. **Inglês** em nomes de classe, método,
  variável, tabela e coluna (a linguagem ubíqua do doc 01 fixa o par PT↔EN).

## Banco e migrations

- Schema muda pelo mapeamento XML → `make:migration` → revisar o SQL contra o
  doc 03 → testar `migrate prev` + `migrate` → conferir que um novo `diff` sai vazio.
- **Mudou dado de referência no código** (ex.: permissão nova em
  `DefaultRoles`)? Escrever também uma **migration de dados** para as empresas
  que já existem — o código só alcança as empresas criadas depois.
- Aplicar em dev **e** teste: `doctrine:migrations:migrate` e `... --env=test`.
