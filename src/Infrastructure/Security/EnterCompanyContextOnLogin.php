<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyContext;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\UserId;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Token validado → entra no contexto da empresa dele (e liga o filtro).
 * No firewall stateless o LoginSuccessEvent dispara a cada requisição
 * autenticada, logo antes do controller.
 *
 * Rotas públicas (login por senha) e CLI não passam aqui: rodam sem filtro e
 * os repositórios recebem o companyId explícito (decisão M1.4).
 */
#[AsEventListener]
final readonly class EnterCompanyContextOnLogin
{
    public function __construct(private CompanyContextHolder $holder)
    {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $principal = $event->getUser();

        $context = match (true) {
            $principal instanceof AuthenticatedUser => CompanyContext::forUser(
                CompanyId::fromString($principal->companyId),
                UserId::fromString($principal->userId),
            ),
            $principal instanceof AuthenticatedDevice => CompanyContext::forDevice(
                CompanyId::fromString($principal->companyId),
                BranchId::fromString($principal->branchId),
            ),
            default => null,
        };

        if (null !== $context) {
            $this->holder->enter($context);
        }
    }
}
