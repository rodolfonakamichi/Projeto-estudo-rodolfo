<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Identity\Auth\TokenPolicy;
use App\Domain\Identity\ApiTokenRepository;
use App\Domain\Identity\DeviceRepository;
use App\Domain\Identity\InvalidCredentials;
use App\Domain\Identity\OpaqueToken;
use App\Domain\Identity\TokenKind;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Ponte entre o `access_token` do Symfony Security e o nosso Domain:
 * `Authorization: Bearer u_...|d_...` → acha pelo hash → valida → renova o uso.
 *
 * O flush aqui é explícito: a autenticação acontece antes de qualquer caso de
 * uso, fora do command.bus (que é quem normalmente dá o flush).
 */
final readonly class OpaqueTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private ApiTokenRepository $tokens,
        private DeviceRepository $devices,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private TokenPolicy $policy,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        try {
            $token = OpaqueToken::fromPlain($accessToken);

            $principal = match ($token->kind()) {
                TokenKind::User => $this->user($token),
                TokenKind::Device => $this->device($token),
            };
        } catch (InvalidCredentials) {
            throw new BadCredentialsException('Token inválido ou expirado.');
        }

        $this->entityManager->flush();

        return new UserBadge($principal->getUserIdentifier(), static fn () => $principal);
    }

    private function user(OpaqueToken $token): AuthenticatedUser
    {
        $apiToken = $this->tokens->findByToken($token) ?? throw new InvalidCredentials();
        $apiToken->use($this->clock->now(), $this->policy->userTokenIdle);

        return new AuthenticatedUser($apiToken->user()->id()->toString(), $apiToken->companyId()->toString(), $token->plain());
    }

    private function device(OpaqueToken $token): AuthenticatedDevice
    {
        $device = $this->devices->findByToken($token) ?? throw new InvalidCredentials();
        $device->use($this->clock->now());

        return new AuthenticatedDevice($device->id()->toString(), $device->companyId()->toString(), $device->branchId()->toString());
    }
}
