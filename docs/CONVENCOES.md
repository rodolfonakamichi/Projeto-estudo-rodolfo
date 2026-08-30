# Convenções do projeto

Regras práticas que valem para todo o código e documentação. O que é decisão
arquitetural com contexto/consequência fica nas ADRs (`docs/dominio/00-...`).

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
- Formatação: **php-cs-fixer** (config em `.php-cs-fixer.php`). Não formatar na
  mão; rodar o fixer.
- Análise estática: **PHPStan** nível 6+ deve passar sem erro.
- `final` por padrão em classes (VOs, entidades, casos de uso, serviços).
  Abrir para herança só com motivo.
- Value Objects: imutáveis (`readonly`), construtor privado + *named
  constructors* (`fromCents`, `fromString`), igualdade por valor (`equals`).

## Testes

- **PHPUnit**. Arquivo espelha `src/` em `tests/`, namespace `Tests\`.
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
