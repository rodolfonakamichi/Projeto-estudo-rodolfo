<?php

declare(strict_types=1);

namespace App\Tests\Domain\Catalog;

use App\Domain\Catalog\OperatorFavorites;
use App\Domain\Catalog\OperatorFavoritesId;
use App\Domain\Catalog\ProductId;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\UserId;
use App\Domain\Shared\DomainException;
use PHPUnit\Framework\TestCase;

final class OperatorFavoritesTest extends TestCase
{
    public function test_troca_a_lista_mantendo_a_ordem_dada(): void
    {
        [$chopp, $burger, $fries] = [ProductId::generate(), ProductId::generate(), ProductId::generate()];
        $favorites = $this->favorites();

        $favorites->replace([$chopp, $burger]);
        self::assertSame($this->strings([$chopp, $burger]), $this->strings($favorites->productIds()));

        $favorites->replace([$fries, $chopp]);
        self::assertSame($this->strings([$fries, $chopp]), $this->strings($favorites->productIds()), 'reordena, tira o que saiu, põe o novo');

        $favorites->replace([]);
        self::assertSame([], $favorites->productIds());
    }

    public function test_produto_repetido_e_recusado(): void
    {
        $chopp = ProductId::generate();

        $this->expectException(DomainException::class);
        $this->favorites()->replace([$chopp, $chopp]);
    }

    public function test_limite_de_favoritos(): void
    {
        $this->expectException(DomainException::class);
        $this->favorites()->replace(array_map(static fn (): ProductId => ProductId::generate(), range(0, OperatorFavorites::MAX)));
    }

    private function favorites(): OperatorFavorites
    {
        return OperatorFavorites::start(OperatorFavoritesId::generate(), CompanyId::generate(), BranchId::generate(), UserId::generate());
    }

    /**
     * @param list<ProductId> $productIds
     *
     * @return list<string>
     */
    private function strings(array $productIds): array
    {
        return array_map(static fn (ProductId $productId): string => $productId->toString(), $productIds);
    }
}
