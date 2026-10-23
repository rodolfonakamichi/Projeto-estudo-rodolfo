<?php

declare(strict_types=1);

namespace App\Application\Identity\Auth;

use App\Application\Shared\Bus\CommandHandler;
use App\Domain\Identity\ApiToken;
use App\Domain\Identity\ApiTokenId;
use App\Domain\Identity\ApiTokenRepository;
use App\Domain\Identity\DeviceId;
use App\Domain\Identity\DeviceRepository;
use App\Domain\Identity\InvalidCredentials;
use App\Domain\Identity\OpaqueToken;
use App\Domain\Identity\PasswordHasher;
use App\Domain\Identity\UserId;
use App\Domain\Identity\UserNotFound;
use App\Domain\Identity\UserRepository;
use Psr\Clock\ClockInterface;

/**
 * Troca o PIN por um token de usuário, a partir de um aparelho pareado.
 * O usuário precisa ter papel na filial do aparelho.
 */
final readonly class LoginWithPinHandler implements CommandHandler
{
    public function __construct(
        private DeviceRepository $devices,
        private UserRepository $users,
        private ApiTokenRepository $tokens,
        private PasswordHasher $hasher,
        private ClockInterface $clock,
        private TokenPolicy $policy,
    ) {
    }

    public function __invoke(LoginWithPin $command): void
    {
        $device = $this->devices->get(DeviceId::fromString($command->deviceId));

        try {
            $user = $this->users->get(UserId::fromString($command->userId));
        } catch (UserNotFound|\InvalidArgumentException) {
            throw new InvalidCredentials();
        }

        $pinHash = $user->pinHash();
        if (!$device->isActive()
            || !$user->companyId()->equals($device->companyId())
            || !$user->worksAt($device->branchId())
            || null === $pinHash
            || !$this->hasher->verify($pinHash, $command->pin)
        ) {
            throw new InvalidCredentials();
        }

        $this->tokens->save(ApiToken::issue(
            ApiTokenId::fromString($command->tokenId),
            $user,
            OpaqueToken::fromPlain($command->plainToken),
            "PIN @ {$device->name()}",
            $this->clock->now(),
            $this->policy->userTokenIdle,
        ));
    }
}
