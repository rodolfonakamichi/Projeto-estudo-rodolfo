<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Filter;

use App\Domain\Identity\Company;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * "Global scope" do Doctrine (ADR-004): acrescenta `company_id = :company_id`
 * no SQL de TODA consulta do ORM — find(), findBy(), DQL, JOINs e carga
 * preguiçosa de associações. Ligado pelo CompanyContextHolder.
 *
 * Convenção: entidade da empresa = tem a associação `company`. Filhas de
 * agregado sem `company` (UserRole, RolePermission) só são alcançáveis pela
 * raiz, que já é filtrada — o CompanyFilterCoverageTest trava essa lista.
 *
 * Não cobre DBAL/SQL puro (read models em Persistence\Dbal): lá o filtro por
 * empresa é escrito à mão.
 */
final class CompanyFilter extends SQLFilter
{
    public const NAME = 'company';
    public const PARAMETER = 'company_id';

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        $column = match (true) {
            Company::class === $targetEntity->getName() => $targetEntity->getSingleIdentifierColumnName(),
            $targetEntity->hasAssociation('company') => $targetEntity->getSingleAssociationJoinColumnName('company'),
            default => null,
        };

        if (null === $column) {
            return '';
        }

        // getParameter() devolve o valor já escapado e entre aspas
        return \sprintf('%s.%s = %s', $targetTableAlias, $column, $this->getParameter(self::PARAMETER));
    }
}
