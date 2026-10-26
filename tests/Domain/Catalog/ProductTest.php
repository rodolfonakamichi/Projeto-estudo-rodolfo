<?php

declare(strict_types=1);

namespace App\Tests\Domain\Catalog;

use App\Domain\Catalog\ModifierGroupId;
use App\Domain\Catalog\ModifierId;
use App\Domain\Catalog\NewModifier;
use App\Domain\Catalog\NewVariant;
use App\Domain\Catalog\Product;
use App\Domain\Catalog\ProductId;
use App\Domain\Catalog\ProductVariantId;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\Money;
use App\Domain\Shared\NotFound;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    /** Aceite M2 (doc 07): Chopp com variações 300ml/500ml — o "Tamanho" é a escolha da variação. */
    public function test_chopp_com_duas_variacoes(): void
    {
        $chopp = $this->product('Chopp', [new NewVariant(ProductVariantId::generate(), '300ml', Money::fromCents(1200)), new NewVariant(ProductVariantId::generate(), '500ml', Money::fromCents(1800))]);

        self::assertSame(['300ml', '500ml'], array_map(static fn ($variant): string => $variant->name(), $chopp->activeVariants()));
        self::assertSame(1800, $chopp->activeVariants()[1]->price()->cents());
    }

    /** Aceite M2 (doc 07): X-Burger com "Ponto" (1 obrigatório) e "Adicionais" (0–5). */
    public function test_x_burger_com_ponto_obrigatorio_e_adicionais_opcionais(): void
    {
        $burger = $this->product('X-Burger');

        $doneness = $burger->addModifierGroup(ModifierGroupId::generate(), 'Ponto', 1, 1, $this->modifiers(['Mal passado' => 0, 'Ao ponto' => 0, 'Bem passado' => 0]));
        $extras = $burger->addModifierGroup(ModifierGroupId::generate(), 'Adicionais', 0, 5, $this->modifiers(['Bacon' => 400, 'Ovo' => 300, 'Cheddar' => 350]));

        self::assertTrue($doneness->isRequired());
        self::assertFalse($extras->isRequired());
        self::assertSame(5, $extras->maxSelect());
        self::assertSame(['Ponto', 'Adicionais'], array_map(static fn ($group): string => $group->name(), $burger->modifierGroups()));
    }

    public function test_sem_variacao_nao_ha_preco(): void
    {
        $this->expectException(DomainException::class);
        Product::create(ProductId::generate(), CompanyId::generate(), BranchId::generate(), 'Água', []);
    }

    public function test_nao_desativa_a_ultima_variacao_ativa(): void
    {
        $product = $this->product('Água');

        $this->expectException(DomainException::class);
        $product->deactivateVariant($product->variants()[0]->id());
    }

    public function test_desativa_variacao_quando_ha_outra_ativa(): void
    {
        $product = $this->product('Chopp', [new NewVariant(ProductVariantId::generate(), '300ml', Money::fromCents(1200)), new NewVariant(ProductVariantId::generate(), '500ml', Money::fromCents(1800))]);
        $product->deactivateVariant($product->variants()[0]->id());

        self::assertCount(1, $product->activeVariants());
    }

    public function test_nome_de_variacao_unico_no_produto(): void
    {
        $product = $this->product('Chopp');

        $this->expectException(DomainException::class);
        $product->addVariant(new NewVariant(ProductVariantId::generate(), ' ÚNICA ', Money::fromCents(100)));
    }

    public function test_preco_negativo_e_recusado_mas_delta_negativo_e_aceito(): void
    {
        $burger = $this->product('X-Burger');
        $group = $burger->addModifierGroup(ModifierGroupId::generate(), 'Pão', 0, 1, $this->modifiers(['Sem pão' => -200]));
        self::assertSame(-200, $group->modifiers()[0]->priceDelta()->cents());

        $this->expectException(DomainException::class);
        $burger->changeVariantPrice($burger->variants()[0]->id(), Money::fromCents(-1));
    }

    /** @return iterable<string, array{int, int, array<string, int>}> */
    public static function limitesInvalidos(): iterable
    {
        yield 'mínimo maior que máximo' => [3, 2, ['A' => 0, 'B' => 0, 'C' => 0]];
        yield 'máximo zero' => [0, 0, ['A' => 0]];
        yield 'mínimo negativo' => [-1, 1, ['A' => 0]];
        yield 'mínimo inalcançável' => [2, 2, ['A' => 0]];
    }

    /** @param array<string, int> $modifiers */
    #[\PHPUnit\Framework\Attributes\DataProvider('limitesInvalidos')]
    public function test_limites_do_grupo_sao_coerentes(int $minSelect, int $maxSelect, array $modifiers): void
    {
        $this->expectException(DomainException::class);
        $this->product('X-Burger')->addModifierGroup(ModifierGroupId::generate(), 'Grupo', $minSelect, $maxSelect, $this->modifiers($modifiers));
    }

    public function test_desativar_modificador_nao_pode_tornar_o_grupo_impossivel(): void
    {
        $burger = $this->product('X-Burger');
        $group = $burger->addModifierGroup(ModifierGroupId::generate(), 'Ponto', 1, 1, $this->modifiers(['Ao ponto' => 0, 'Bem passado' => 0]));

        $burger->deactivateModifier($group->id(), $group->modifiers()[0]->id());
        self::assertCount(1, $group->activeModifiers());

        $this->expectException(DomainException::class);
        $burger->deactivateModifier($group->id(), $group->modifiers()[1]->id());
    }

    public function test_grupo_de_outro_produto_nao_e_encontrado(): void
    {
        $this->expectException(NotFound::class);
        $this->product('X-Burger')->changeModifierGroupLimits(ModifierGroupId::generate(), 0, 1);
    }

    /** @param list<NewVariant>|null $variants */
    private function product(string $name, ?array $variants = null): Product
    {
        return Product::create(
            ProductId::generate(),
            CompanyId::generate(),
            BranchId::generate(),
            $name,
            $variants ?? [new NewVariant(ProductVariantId::generate(), 'Única', Money::fromCents(2500))],
        );
    }

    /**
     * @param array<string, int> $pricesByName
     *
     * @return list<NewModifier>
     */
    private function modifiers(array $pricesByName): array
    {
        $modifiers = [];
        foreach ($pricesByName as $name => $cents) {
            $modifiers[] = new NewModifier(ModifierId::generate(), $name, Money::fromCents($cents));
        }

        return $modifiers;
    }
}
