<?php

declare(strict_types=1);

namespace App\Tests\Domain\Catalog;

use App\Domain\Catalog\Category;
use App\Domain\Catalog\CategoryId;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Shared\DomainException;
use PHPUnit\Framework\TestCase;

final class CategoryTest extends TestCase
{
    public function test_cria_com_nome_aparado_e_ordem(): void
    {
        $category = $this->category('  Bebidas ', 2);

        self::assertSame('Bebidas', $category->name());
        self::assertSame(2, $category->sortOrder());
        self::assertFalse($category->isArchived());
    }

    public function test_nome_vazio_e_recusado(): void
    {
        $this->expectException(DomainException::class);
        $this->category('   ');
    }

    public function test_ordem_negativa_e_recusada(): void
    {
        $this->expectException(DomainException::class);
        $this->category('Bebidas')->moveTo(-1);
    }

    public function test_arquivada_nao_muda_mais(): void
    {
        $category = $this->category('Bebidas');
        $category->archive(new \DateTimeImmutable('2026-10-26 10:00'));

        self::assertTrue($category->isArchived());

        $this->expectException(DomainException::class);
        $category->rename('Drinks');
    }

    private function category(string $name, int $sortOrder = 0): Category
    {
        return Category::create(CategoryId::generate(), CompanyId::generate(), BranchId::generate(), $name, $sortOrder);
    }
}
