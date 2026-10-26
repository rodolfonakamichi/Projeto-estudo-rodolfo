<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Application\Identity\RegisterCompany\RegisterCompany;
use App\Application\Shared\Bus\CommandBus;
use App\Domain\Catalog\ModifierGroupId;
use App\Domain\Catalog\ModifierId;
use App\Domain\Catalog\NewModifier;
use App\Domain\Catalog\NewVariant;
use App\Domain\Catalog\Product;
use App\Domain\Catalog\ProductId;
use App\Domain\Catalog\ProductVariantId;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Shared\Money;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** O agregado inteiro vai e volta do MySQL: cascata, ordem, Money, enum. */
final class ProductPersistenceTest extends KernelTestCase
{
    public function test_grava_e_recarrega_o_agregado_inteiro(): void
    {
        $companyId = CompanyId::generate();
        $branchId = BranchId::generate();
        self::getContainer()->get(CommandBus::class)->dispatch(new RegisterCompany($companyId->toString(), 'Bar do Zé', 'bar-do-ze', $branchId->toString(), 'Matriz'));

        $productId = ProductId::generate();
        $burger = Product::create($productId, $companyId, $branchId, 'X-Burger', [new NewVariant(ProductVariantId::generate(), 'X-Burger', Money::fromCents(3200), 'XB-01')]);
        $burger->addModifierGroup(ModifierGroupId::generate(), 'Ponto', 1, 1, [
            new NewModifier(ModifierId::generate(), 'Ao ponto', Money::fromCents(0)),
            new NewModifier(ModifierId::generate(), 'Bem passado', Money::fromCents(0)),
        ]);
        $burger->addModifierGroup(ModifierGroupId::generate(), 'Adicionais', 0, 5, [
            new NewModifier(ModifierId::generate(), 'Bacon', Money::fromCents(400)),
        ]);

        // sem consumidor no container até os casos de uso do M2.3: monta o adapter direto
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $repository = new DoctrineProductRepository($entityManager);
        $repository->save($burger);
        $entityManager->flush();
        $entityManager->clear();

        $reloaded = $repository->get($productId);

        self::assertSame('X-Burger', $reloaded->name());
        self::assertSame(3200, $reloaded->variants()[0]->price()->cents());
        self::assertSame('XB-01', $reloaded->variants()[0]->sku());
        self::assertSame(['Ponto', 'Adicionais'], array_map(static fn ($group): string => $group->name(), $reloaded->modifierGroups()));
        self::assertSame(['Ao ponto', 'Bem passado'], array_map(static fn ($modifier): string => $modifier->name(), $reloaded->modifierGroups()[0]->modifiers()));
        self::assertSame(400, $reloaded->modifierGroups()[1]->modifiers()[0]->priceDelta()->cents());
        self::assertTrue($reloaded->modifierGroups()[0]->isRequired());
        self::assertNull($reloaded->categoryId());
    }
}
