<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Identity\Authorization\PermissionChecker;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\Permission;
use App\Domain\Identity\UserId;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Adapter: liga o `isGranted()` / `#[IsGranted]` do Symfony ao nosso
 * PermissionChecker. Atributo = código do doc 06 (`command.close`);
 * sujeito = filial (BranchId, string do ULID ou null = nível da empresa).
 *
 *   $security->isGranted('command.close', $branchId)
 *   #[IsGranted('command.close', subject: 'branchId')]
 *
 * Aparelho pareado (sem usuário) nunca tem permissão de negócio.
 *
 * @extends Voter<string, BranchId|string|null>
 */
final class PermissionVoter extends Voter
{
    public function __construct(private readonly PermissionChecker $checker)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return null !== Permission::tryFrom($attribute)
            && (null === $subject || $subject instanceof BranchId || \is_string($subject));
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $principal = $token->getUser();
        if (!$principal instanceof AuthenticatedUser) {
            return false;
        }

        return $this->checker->can(
            UserId::fromString($principal->userId),
            Permission::from($attribute),
            \is_string($subject) ? BranchId::fromString($subject) : $subject,
        );
    }
}
