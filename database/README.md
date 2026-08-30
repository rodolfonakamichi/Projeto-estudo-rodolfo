# database

Migrations e seeds do **Phinx**. O `phinx.php` (raiz) aponta para cá e lê as
credenciais do `.env` via `config/env.php`.

- `migrations/` — evolução do schema, uma migration por mudança coesa
  (`CreateCommandsTable`, `AddServiceFeeToCommands`).
- `seeds/` — dados de referência (papéis, permissões, tenant demo).

O **contrato do schema** é `docs/dominio/03-modelo-de-dados.md`; o racional de
usar Phinx sem framework está na ADR-011 (`docs/dominio/00-decisoes-arquiteturais.md`).
Divergência entre a migration e o documento 03 é bug.

## Comandos

```bash
docker compose exec php vendor/bin/phinx status              # o que já rodou
docker compose exec php vendor/bin/phinx create CreateXTable # nova migration
docker compose exec php vendor/bin/phinx migrate             # aplica as pendentes
docker compose exec php vendor/bin/phinx rollback            # desfaz a última
docker compose exec php vendor/bin/phinx seed:run            # roda os seeds
```

Toda `up()` deve ter `down()` reversível enquanto em desenvolvimento.
