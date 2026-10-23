# tests

Testes automatizados (PHPUnit), espelhando a estrutura de `src/`.
Namespace `App\Tests\`. Rodar: `bin/phpunit`.

- `Domain/` — unitários puros (`TestCase`), sem kernel nem banco.
- `Presentation/Http/` — funcionais (`WebTestCase`): sobem o kernel e fazem
  requisições HTTP simuladas.
