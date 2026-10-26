<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Entidade com `created_at`/`updated_at`. Quem chama `touch()` é a
 * infraestrutura ao gravar (listener do Doctrine) — o Domain não lê relógio.
 */
interface Timestamped
{
    public function touch(\DateTimeImmutable $now): void;
}
