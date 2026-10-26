<?php

declare(strict_types=1);

namespace App\Application\Identity\RegisterUser;

use App\Application\Shared\Bus\CommandHandler;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\CompanyRepository;
use App\Domain\Identity\Email;
use App\Domain\Identity\PasswordHasher;
use App\Domain\Identity\RoleCode;
use App\Domain\Identity\RoleRepository;
use App\Domain\Identity\User;
use App\Domain\Identity\UserId;
use App\Domain\Identity\UserRepository;
use App\Domain\Shared\DomainException;

final readonly class RegisterUserHandler implements CommandHandler
{
    private const MIN_PASSWORD = 8;

    public function __construct(
        private CompanyRepository $companies,
        private UserRepository $users,
        private RoleRepository $roles,
        private PasswordHasher $hasher,
    ) {
    }

    public function __invoke(RegisterUser $command): void
    {
        $company = $this->companies->get(CompanyId::fromString($command->companyId));
        $email = Email::fromString($command->email);

        if ($this->users->emailExists($company->id(), $email)) {
            throw new DomainException("Já existe um usuário com o e-mail {$email->toString()} nesta empresa.");
        }
        if (mb_strlen($command->password) < self::MIN_PASSWORD) {
            throw new DomainException(\sprintf('A senha precisa de ao menos %d caracteres.', self::MIN_PASSWORD));
        }

        $user = User::register(
            UserId::fromString($command->userId),
            $company,
            $command->name,
            $email,
            $this->hasher->hash($command->password),
        );

        if (null !== $command->pin) {
            if (1 !== preg_match('/^\d{4,6}$/', $command->pin)) {
                throw new DomainException('O PIN deve ter de 4 a 6 dígitos.');
            }
            $user->changePin($this->hasher->hash($command->pin));
        }

        foreach ($command->roles as $assignment) {
            $role = $this->roles->getByCode($company->id(), RoleCode::fromString($assignment->roleCode));
            $branch = null === $assignment->branchId ? null : $company->branch(BranchId::fromString($assignment->branchId));
            $user->assignRole($role, $branch);
        }

        $this->users->save($user);
    }
}
