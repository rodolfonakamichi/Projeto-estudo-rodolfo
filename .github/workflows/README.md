# .github/workflows

Workflows do GitHub Actions.

- **`ci.yml`** — roda a cada push na `main` e em todo pull request:
  valida o `composer.json`, checa que todo arquivo versionado é UTF-8, e roda
  php-cs-fixer (`--dry-run`), PHPStan e PHPUnit. Se qualquer step falhar, o
  commit/PR fica marcado como quebrado.

Usa `shivammathur/setup-php` (PHP 8.3 no runner), não o Docker do projeto — os
testes atuais são puros e não precisam de MySQL. Quando entrarem testes que
dependem do banco (M1+), adicionar um `services: mysql` ao job.
