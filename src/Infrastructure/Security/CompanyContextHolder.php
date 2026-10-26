<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Shared\Context\CompanyContextProvider;
use App\Domain\Identity\CompanyContext;
use App\Infrastructure\Persistence\Doctrine\Filter\CompanyFilter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Guarda o CompanyContext da requisição e liga o CompanyFilter do Doctrine
 * no mesmo passo — contexto e filtro nunca divergem.
 *
 * Mutável de propósito: o container cria o serviço uma vez, o contexto só
 * aparece no meio da requisição. ResetInterface: em processo longo (worker do
 * Messenger, FrankenPHP worker mode) o `services_resetter` chama reset() entre
 * uma mensagem/requisição e outra, e a empresa anterior não vaza para a próxima.
 */
final class CompanyContextHolder implements CompanyContextProvider, ResetInterface
{
    private ?CompanyContext $context = null;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function enter(CompanyContext $context): void
    {
        if (null !== $this->context && !$this->context->companyId->equals($context->companyId)) {
            throw new \LogicException('Já existe outra empresa no contexto desta requisição.');
        }

        $this->context = $context;
        $this->em->getFilters()
            ->enable(CompanyFilter::NAME)
            ->setParameter(CompanyFilter::PARAMETER, $context->companyId->toString());
    }

    public function get(): CompanyContext
    {
        return $this->context
            ?? throw new \LogicException('CompanyContext não resolvido — requisição sem autenticação?');
    }

    public function has(): bool
    {
        return null !== $this->context;
    }

    public function reset(): void
    {
        $this->context = null;

        $filters = $this->em->getFilters();
        if ($filters->isEnabled(CompanyFilter::NAME)) {
            $filters->disable(CompanyFilter::NAME);
        }
    }
}
