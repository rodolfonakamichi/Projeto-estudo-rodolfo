<?php

declare(strict_types=1);

namespace App\Presentation\Http\Auth;

use App\Application\Identity\Auth\DeviceUsersQuery;
use App\Application\Identity\Auth\LoginWithPassword;
use App\Application\Identity\Auth\LoginWithPin;
use App\Application\Identity\Auth\Logout;
use App\Application\Identity\Auth\PairDevice;
use App\Application\Shared\Bus\CommandBus;
use App\Domain\Identity\ApiTokenId;
use App\Domain\Identity\DeviceId;
use App\Domain\Identity\InvalidCredentials;
use App\Domain\Identity\OpaqueToken;
use App\Domain\Identity\TokenKind;
use App\Domain\Identity\UserId;
use App\Domain\Identity\UserRepository;
use App\Domain\Identity\UserRole;
use App\Infrastructure\Security\AuthenticatedDevice;
use App\Infrastructure\Security\AuthenticatedUser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Adaptador HTTP da autenticação. Não tem regra: valida o formato (DTO),
 * aplica o rate limit, gera token/IDs, despacha o caso de uso e monta o JSON.
 */
#[Route('/api')]
final readonly class AuthController
{
    public function __construct(
        private CommandBus $bus,
        #[Autowire(service: 'limiter.login')] private RateLimiterFactoryInterface $loginLimiter,
    ) {
    }

    #[Route('/auth/login', methods: ['POST'])]
    public function login(#[MapRequestPayload] LoginRequest $body, Request $request): JsonResponse
    {
        $token = OpaqueToken::generate(TokenKind::User);

        $this->throttled("pwd|{$body->company}|".mb_strtolower($body->email)."|{$request->getClientIp()}", fn () => $this->bus->dispatch(new LoginWithPassword(
            tokenId: ApiTokenId::generate()->toString(),
            plainToken: $token->plain(),
            companySlug: $body->company,
            email: $body->email,
            password: $body->password,
            tokenName: mb_substr((string) $request->headers->get('User-Agent', 'login'), 0, 80),
        )));

        return new JsonResponse(['token' => $token->plain()], Response::HTTP_CREATED);
    }

    /** Tela de PIN: quem pode logar neste aparelho. */
    #[Route('/device/users', methods: ['GET'])]
    public function deviceUsers(#[CurrentUser] AuthenticatedDevice $device, DeviceUsersQuery $query): JsonResponse
    {
        return new JsonResponse(['users' => $query->forDevice(DeviceId::fromString($device->deviceId))]);
    }

    #[Route('/auth/pin', methods: ['POST'])]
    public function pin(#[CurrentUser] AuthenticatedDevice $device, #[MapRequestPayload] PinLoginRequest $body, Request $request): JsonResponse
    {
        $token = OpaqueToken::generate(TokenKind::User);

        $this->throttled("pin|{$device->deviceId}|{$body->userId}|{$request->getClientIp()}", fn () => $this->bus->dispatch(new LoginWithPin(
            tokenId: ApiTokenId::generate()->toString(),
            plainToken: $token->plain(),
            deviceId: $device->deviceId,
            userId: $body->userId,
            pin: $body->pin,
        )));

        return new JsonResponse(['token' => $token->plain()], Response::HTTP_CREATED);
    }

    #[Route('/auth/logout', methods: ['POST'])]
    public function logout(#[CurrentUser] AuthenticatedUser $user): Response
    {
        $this->bus->dispatch(new Logout($user->plainToken));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/devices', methods: ['POST'])]
    public function pairDevice(#[CurrentUser] AuthenticatedUser $user, #[MapRequestPayload] PairDeviceRequest $body): JsonResponse
    {
        $deviceId = DeviceId::generate();
        $token = OpaqueToken::generate(TokenKind::Device);

        $this->bus->dispatch(new PairDevice($deviceId->toString(), $token->plain(), $user->userId, $body->branchId, $body->name));

        return new JsonResponse(['deviceId' => $deviceId->toString(), 'token' => $token->plain()], Response::HTTP_CREATED);
    }

    #[Route('/me', methods: ['GET'])]
    public function me(#[CurrentUser] AuthenticatedUser $principal, UserRepository $users): JsonResponse
    {
        $user = $users->get(UserId::fromString($principal->userId));

        return new JsonResponse([
            'id' => $user->id()->toString(),
            'companyId' => $user->companyId()->toString(),
            'name' => $user->name(),
            'email' => $user->email()->toString(),
            'roles' => array_map(static fn (UserRole $assignment): array => [
                'code' => $assignment->role()->code()->toString(),
                'branchId' => $assignment->branchId()?->toString(),
            ], $user->roleAssignments()),
        ]);
    }

    /**
     * Rate limit (doc: 5 tentativas / 15 min por alvo+IP). Conta só as
     * FALHAS: login certo zera o contador.
     */
    private function throttled(string $key, callable $attempt): void
    {
        $limiter = $this->loginLimiter->create($key);

        // consume(0) só consulta: 0 restantes = estourou as 5 falhas da janela
        if (0 === $limiter->consume(0)->getRemainingTokens()) {
            throw new TooManyRequestsHttpException(900, 'Muitas tentativas. Tente de novo em alguns minutos.');
        }

        try {
            $attempt();
        } catch (InvalidCredentials $exception) {
            $limiter->consume();

            throw $exception;
        }

        $limiter->reset();
    }
}
