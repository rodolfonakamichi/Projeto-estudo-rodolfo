<?php

declare(strict_types=1);

namespace App\Presentation\Http\Catalog;

use App\Application\Catalog\CatalogQuery;
use App\Application\Catalog\Category\ArchiveCategory;
use App\Application\Catalog\Category\CreateCategory;
use App\Application\Catalog\Category\UpdateCategory;
use App\Application\Shared\Bus\CommandBus;
use App\Domain\Catalog\CategoryId;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Infrastructure\Security\AuthenticatedUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Categorias do cardápio da filial. DELETE arquiva (não apaga). */
#[Route('/api')]
final readonly class CategoryController
{
    public function __construct(private CommandBus $bus)
    {
    }

    #[Route('/branches/{branchId}/categories', methods: ['GET'], requirements: ['branchId' => Requirement::ULID])]
    public function list(#[CurrentUser] AuthenticatedUser $user, string $branchId, CatalogQuery $catalog): JsonResponse
    {
        return new JsonResponse(['categories' => $catalog->categories(CompanyId::fromString($user->companyId), BranchId::fromString($branchId))]);
    }

    #[Route('/branches/{branchId}/categories', methods: ['POST'], requirements: ['branchId' => Requirement::ULID])]
    public function create(#[CurrentUser] AuthenticatedUser $user, string $branchId, #[MapRequestPayload] CategoryRequest $body): JsonResponse
    {
        $categoryId = CategoryId::generate();
        $this->bus->dispatch(new CreateCategory($categoryId->toString(), $user->userId, $branchId, $body->name, $body->sortOrder));

        return new JsonResponse(['id' => $categoryId->toString()], Response::HTTP_CREATED);
    }

    #[Route('/categories/{categoryId}', methods: ['PATCH'], requirements: ['categoryId' => Requirement::ULID])]
    public function update(#[CurrentUser] AuthenticatedUser $user, string $categoryId, #[MapRequestPayload] UpdateCategoryRequest $body): Response
    {
        $this->bus->dispatch(new UpdateCategory($categoryId, $user->userId, $body->name, $body->sortOrder));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/categories/{categoryId}', methods: ['DELETE'], requirements: ['categoryId' => Requirement::ULID])]
    public function archive(#[CurrentUser] AuthenticatedUser $user, string $categoryId): Response
    {
        $this->bus->dispatch(new ArchiveCategory($categoryId, $user->userId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
