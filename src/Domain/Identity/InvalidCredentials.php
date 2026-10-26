<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Shared\DomainException;

/**
 * Login/token recusado. Mensagem SEMPRE genérica: não dizer se foi a empresa,
 * o e-mail ou a senha que falhou (evita descobrir quem tem conta).
 */
final class InvalidCredentials extends DomainException
{
    public function __construct()
    {
        parent::__construct('Credenciais inválidas.');
    }
}
