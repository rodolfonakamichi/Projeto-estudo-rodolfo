<?php

declare(strict_types=1);

namespace App\Tests\Integration\MultiCompany;

use App\Domain\Catalog\FavoriteProduct;
use App\Domain\Catalog\Modifier;
use App\Domain\Catalog\ModifierGroup;
use App\Domain\Catalog\ProductVariant;
use App\Domain\Identity\RolePermission;
use App\Domain\Identity\UserRole;
use App\Infrastructure\Persistence\Doctrine\Filter\CompanyFilter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Rede de segurança da convenção do CompanyFilter: toda entidade mapeada é
 * filtrada por empresa OU está na lista explícita de filhas de agregado.
 * Entidade nova sem a associação `company` quebra este teste — de propósito.
 */
final class CompanyFilterCoverageTest extends KernelTestCase
{
    /** Filhas alcançáveis só pela raiz (que é filtrada). Adicionar aqui é decisão consciente. */
    private const AGGREGATE_CHILDREN = [
        RolePermission::class, // raiz: Role
        UserRole::class,       // raiz: User
        ProductVariant::class, // raiz: Product
        ModifierGroup::class,  // raiz: Product
        Modifier::class,       // raiz: Product (via ModifierGroup)
        FavoriteProduct::class, // raiz: OperatorFavorites
    ];

    public function test_toda_entidade_e_filtrada_ou_filha_declarada(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $filter = new CompanyFilter($entityManager);
        $filter->setParameter(CompanyFilter::PARAMETER, '01HZZZZZZZZZZZZZZZZZZZZZZZ');

        $unfiltered = [];
        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $constraint = $filter->addFilterConstraint($metadata, 't0');

            if ('' === $constraint) {
                $unfiltered[] = $metadata->getName();
            } else {
                self::assertStringContainsString("'01HZZZZZZZZZZZZZZZZZZZZZZZ'", $constraint);
            }
        }

        sort($unfiltered);
        $expected = self::AGGREGATE_CHILDREN;
        sort($expected);

        self::assertSame($expected, $unfiltered);
    }
}
