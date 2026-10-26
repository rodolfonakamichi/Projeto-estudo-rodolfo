<?php

declare(strict_types=1);

namespace App\Presentation\Http\Catalog;

use App\Application\Catalog\CatalogQuery;
use App\Application\Catalog\Product\AddModifier;
use App\Application\Catalog\Product\AddModifierGroup;
use App\Application\Catalog\Product\AddProductVariant;
use App\Application\Catalog\Product\ChangeModifierGroupLimits;
use App\Application\Catalog\Product\ChangeModifierStatus;
use App\Application\Catalog\Product\CreateProduct;
use App\Application\Catalog\Product\ModifierGroupInput;
use App\Application\Catalog\Product\ModifierInput;
use App\Application\Catalog\Product\UpdateProduct;
use App\Application\Catalog\Product\UpdateProductVariant;
use App\Application\Catalog\Product\VariantInput;
use App\Application\Shared\Bus\CommandBus;
use App\Domain\Catalog\CategoryId;
use App\Domain\Catalog\ModifierGroupId;
use App\Domain\Catalog\ModifierId;
use App\Domain\Catalog\ProductId;
use App\Domain\Catalog\ProductNotFound;
use App\Domain\Catalog\ProductVariantId;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Infrastructure\Security\AuthenticatedUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Produtos do cardápio da filial. O controller gera os IDs (ULID, inclusive
 * das variações/grupos/modificadores), despacha o caso de uso e devolve o
 * produto como ficou — lido do read model, já com os IDs das partes.
 */
#[Route('/api')]
final readonly class ProductController
{
    public function __construct(
        private CommandBus $bus,
        private CatalogQuery $catalog,
    ) {
    }

    #[Route('/branches/{branchId}/products', methods: ['GET'], requirements: ['branchId' => Requirement::ULID])]
    public function list(#[CurrentUser] AuthenticatedUser $user, string $branchId, #[MapQueryParameter] ?string $categoryId = null): JsonResponse
    {
        return new JsonResponse(['products' => $this->catalog->products(
            CompanyId::fromString($user->companyId),
            BranchId::fromString($branchId),
            null === $categoryId ? null : CategoryId::fromString($categoryId),
        )]);
    }

    /** Busca rápida (doc 07, M2.4): `?q=cho` acha "Chopp". Prefixo de palavra, mínimo 3 letras. */
    #[Route('/branches/{branchId}/catalog/search', methods: ['GET'], requirements: ['branchId' => Requirement::ULID])]
    public function search(#[CurrentUser] AuthenticatedUser $user, string $branchId, #[MapQueryParameter] string $q = ''): JsonResponse
    {
        return new JsonResponse(['products' => $this->catalog->search(
            CompanyId::fromString($user->companyId),
            BranchId::fromString($branchId),
            mb_substr($q, 0, 100),
        )]);
    }

    #[Route('/products/{productId}', methods: ['GET'], requirements: ['productId' => Requirement::ULID])]
    public function show(#[CurrentUser] AuthenticatedUser $user, string $productId): JsonResponse
    {
        return $this->product($user, $productId);
    }

    #[Route('/branches/{branchId}/products', methods: ['POST'], requirements: ['branchId' => Requirement::ULID])]
    public function create(#[CurrentUser] AuthenticatedUser $user, string $branchId, #[MapRequestPayload] ProductRequest $body): JsonResponse
    {
        $productId = ProductId::generate()->toString();

        $this->bus->dispatch(new CreateProduct(
            productId: $productId,
            actorId: $user->userId,
            branchId: $branchId,
            name: $body->name,
            variants: array_map(self::variant(...), $body->variants),
            modifierGroups: array_map(self::group(...), $body->modifierGroups),
            categoryId: $body->categoryId,
            stationId: $body->stationId,
            trackStock: $body->trackStock,
        ));

        return $this->product($user, $productId, Response::HTTP_CREATED);
    }

    #[Route('/products/{productId}', methods: ['PATCH'], requirements: ['productId' => Requirement::ULID])]
    public function update(#[CurrentUser] AuthenticatedUser $user, string $productId, #[MapRequestPayload] UpdateProductRequest $body, Request $request): JsonResponse
    {
        // "categoryId": null limpa; chave ausente não mexe — o DTO sozinho não distingue
        $sent = $request->toArray();

        $this->bus->dispatch(new UpdateProduct(
            productId: $productId,
            actorId: $user->userId,
            name: $body->name,
            trackStock: $body->trackStock,
            active: $body->active,
            changeCategory: \array_key_exists('categoryId', $sent),
            categoryId: $body->categoryId,
            changeStation: \array_key_exists('stationId', $sent),
            stationId: $body->stationId,
        ));

        return $this->product($user, $productId);
    }

    #[Route('/products/{productId}/variants', methods: ['POST'], requirements: ['productId' => Requirement::ULID])]
    public function addVariant(#[CurrentUser] AuthenticatedUser $user, string $productId, #[MapRequestPayload] VariantRequest $body): JsonResponse
    {
        $this->bus->dispatch(new AddProductVariant($productId, $user->userId, self::variant($body)));

        return $this->product($user, $productId, Response::HTTP_CREATED);
    }

    #[Route('/products/{productId}/variants/{variantId}', methods: ['PATCH'], requirements: ['productId' => Requirement::ULID, 'variantId' => Requirement::ULID])]
    public function updateVariant(#[CurrentUser] AuthenticatedUser $user, string $productId, string $variantId, #[MapRequestPayload] UpdateVariantRequest $body): JsonResponse
    {
        $this->bus->dispatch(new UpdateProductVariant($productId, $variantId, $user->userId, $body->name, $body->priceCents, $body->active));

        return $this->product($user, $productId);
    }

    #[Route('/products/{productId}/modifier-groups', methods: ['POST'], requirements: ['productId' => Requirement::ULID])]
    public function addModifierGroup(#[CurrentUser] AuthenticatedUser $user, string $productId, #[MapRequestPayload] ModifierGroupRequest $body): JsonResponse
    {
        $this->bus->dispatch(new AddModifierGroup($productId, $user->userId, self::group($body)));

        return $this->product($user, $productId, Response::HTTP_CREATED);
    }

    #[Route('/products/{productId}/modifier-groups/{groupId}', methods: ['PATCH'], requirements: ['productId' => Requirement::ULID, 'groupId' => Requirement::ULID])]
    public function changeGroupLimits(#[CurrentUser] AuthenticatedUser $user, string $productId, string $groupId, #[MapRequestPayload] ModifierGroupLimitsRequest $body): JsonResponse
    {
        $this->bus->dispatch(new ChangeModifierGroupLimits($productId, $groupId, $user->userId, $body->minSelect, $body->maxSelect));

        return $this->product($user, $productId);
    }

    #[Route('/products/{productId}/modifier-groups/{groupId}/modifiers', methods: ['POST'], requirements: ['productId' => Requirement::ULID, 'groupId' => Requirement::ULID])]
    public function addModifier(#[CurrentUser] AuthenticatedUser $user, string $productId, string $groupId, #[MapRequestPayload] ModifierRequest $body): JsonResponse
    {
        $this->bus->dispatch(new AddModifier($productId, $groupId, $user->userId, self::modifier($body)));

        return $this->product($user, $productId, Response::HTTP_CREATED);
    }

    #[Route('/products/{productId}/modifier-groups/{groupId}/modifiers/{modifierId}', methods: ['PATCH'], requirements: ['productId' => Requirement::ULID, 'groupId' => Requirement::ULID, 'modifierId' => Requirement::ULID])]
    public function changeModifierStatus(#[CurrentUser] AuthenticatedUser $user, string $productId, string $groupId, string $modifierId, #[MapRequestPayload] ModifierStatusRequest $body): JsonResponse
    {
        $this->bus->dispatch(new ChangeModifierStatus($productId, $groupId, $modifierId, $user->userId, $body->active));

        return $this->product($user, $productId);
    }

    private function product(AuthenticatedUser $user, string $productId, int $status = Response::HTTP_OK): JsonResponse
    {
        $id = ProductId::fromString($productId);
        $product = $this->catalog->product(CompanyId::fromString($user->companyId), $id) ?? throw ProductNotFound::withId($id);

        return new JsonResponse($product, $status);
    }

    private static function variant(VariantRequest $request): VariantInput
    {
        return new VariantInput(ProductVariantId::generate()->toString(), $request->name, $request->priceCents, $request->sku);
    }

    private static function group(ModifierGroupRequest $request): ModifierGroupInput
    {
        return new ModifierGroupInput(
            ModifierGroupId::generate()->toString(),
            $request->name,
            $request->minSelect,
            $request->maxSelect,
            array_map(self::modifier(...), $request->modifiers),
        );
    }

    private static function modifier(ModifierRequest $request): ModifierInput
    {
        return new ModifierInput(ModifierId::generate()->toString(), $request->name, $request->priceDeltaCents);
    }
}
