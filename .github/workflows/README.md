# .github/workflows

Workflows do GitHub Actions.

- **`ci.yml`** — roda a cada push na `main` e em todo pull request:
  valida o `composer.json`, checa que todo arquivo versionado é UTF-8, e roda
  php-cs-fixer (`--dry-run`), PHPStan (nível 8, lendo o container compilado),
  Deptrac (regra de camadas), `lint:container` e PHPUnit. Localmente, o mesmo
  conjunto roda com `composer check`.

Usa `shivammathur/setup-php` (PHP 8.3 no runner), não o Docker do projeto. Um
`services: mysql` (8.4) sobe junto: o job cria o `comandas_test`, roda as
migrations, confere `doctrine:schema:validate` (mapeamento × banco) e então o
PHPUnit — os testes de integração usam esse banco, cada um numa transação
desfeita no fim (dama/doctrine-test-bundle).
