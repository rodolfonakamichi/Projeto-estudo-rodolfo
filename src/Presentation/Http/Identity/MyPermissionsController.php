<?php

declare(strict_types=1);

namespace App\Presentation\Http\Identity;

use App\Application\Shared\AccessDenied;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\Permission;
use App\Domain\Identity\UserId;
use App\Domain\Identity\UserRepository;
use App\Infrastructure\Security\AuthenticatedUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * "O que eu posso fazer?" — UX contextual (doc 06 §5): a PWA pergunta antes de
 * mostrar o botão. `?branchId=` ausente = nível da empresa.
 * Quem decide é sempre o caso de uso; isto é só leitura.
 */
#[Route('/api/me/permissions')]
final readonly class MyPermissionsController
{
    public function __construct(private AuthorizationCheckerInterface $auth)
    {
    }

    /** Lista as permissões efetivas na filial. */
    #[Route('', methods: ['GET'])]
    public function list(#[CurrentUser] AuthenticatedUser $principal, UserRepository $users, #[MapQueryParameter] ?string $branchId = null): JsonResponse
    {
        $user = $users->get(UserId::fromString($principal->userId));
        $branch = null === $branchId ? null : BranchId::fromString($branchId);

        return new JsonResponse([
            'branchId' => $branch?->toString(),
            'permissions' => array_map(static fn (Permission $permission): string => $permission->value, $user->permissionsIn($branch)),
        ]);
    }

    /** 204 = pode · 403 = não pode · 404 = código de permissão inexistente. Passa pelo PermissionVoter. */
    #[Route('/{code}', methods: ['GET'])]
    public function check(string $code, #[MapQueryParameter] ?string $branchId = null): Response
    {
        $permission = Permission::tryFrom($code) ?? throw new NotFoundHttpException("Permissão desconhecida: {$code}.");
        $branch = null === $branchId ? null : BranchId::fromString($branchId);

        if (!$this->auth->isGranted($permission->value, $branch)) {
            throw AccessDenied::missing($permission->value);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
