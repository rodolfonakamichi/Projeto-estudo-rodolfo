<?php

declare(strict_types=1);

namespace App\Application\Identity\Auth;

use App\Application\Shared\Bus\CommandHandler;
use App\Domain\Identity\ApiToken;
use App\Domain\Identity\ApiTokenId;
use App\Domain\Identity\ApiTokenRepository;
use App\Domain\Identity\CompanyNotFound;
use App\Domain\Identity\CompanyRepository;
use App\Domain\Identity\Email;
use App\Domain\Identity\InvalidCredentials;
use App\Domain\Identity\OpaqueToken;
use App\Domain\Identity\PasswordHasher;
use App\Domain\Identity\Slug;
use App\Domain\Identity\UserRepository;
use Psr\Clock\ClockInterface;

final readonly class LoginWithPasswordHandler implements CommandHandler
{
    public function __construct(
        private CompanyRepository $companies,
        private UserRepository $users,
        private ApiTokenRepository $tokens,
        private PasswordHasher $hasher,
        private ClockInterface $clock,
        private TokenPolicy $policy,
    ) {
    }

    public function __invoke(LoginWithPassword $command): void
    {
        try {
            $company = $this->companies->getBySlug(Slug::fromString($command->companySlug));
            $user = $this->users->findByEmail($company->id(), Email::fromString($command->email));
        } catch (CompanyNotFound|\InvalidArgumentException) {
            $user = null;
        }

        // Sempre roda o verify (mesmo sem usuário) para o tempo de resposta não
        // revelar se o e-mail existe (timing attack).
        $hash = $user?->passwordHash() ?? $this->hasher->hash('timing-dummy');
        $passwordOk = $this->hasher->verify($hash, $command->password);

        if (null === $user || !$passwordOk || !$user->isActive() || !$user->company()->isActive()) {
            throw new InvalidCredentials();
        }

        $this->tokens->save(ApiToken::issue(
            ApiTokenId::fromString($command->tokenId),
            $user,
            OpaqueToken::fromPlain($command->plainToken),
            $command->tokenName,
            $this->clock->now(),
            $this->policy->userTokenIdle,
        ));
    }
}
