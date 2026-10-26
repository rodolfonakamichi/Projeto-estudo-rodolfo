<?php

declare(strict_types=1);

namespace App\Presentation\Http\Error;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/** Token enviado mas recusado (inválido, expirado, revogado) → 401 em JSON. */
final class JsonAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(
            ['error' => 'Token inválido ou expirado.'],
            Response::HTTP_UNAUTHORIZED,
            ['WWW-Authenticate' => 'Bearer error="invalid_token"'],
        );
    }
}
