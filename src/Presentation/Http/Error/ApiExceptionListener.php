<?php

declare(strict_types=1);

namespace App\Presentation\Http\Error;

use App\Application\Shared\AccessDenied;
use App\Domain\Identity\InvalidCredentials;
use App\Domain\Shared\DomainException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Traduz exceções em JSON padronizado nas rotas /api — um lugar só, em vez de
 * try/catch em todo controller:
 *   InvalidCredentials → 401 · AccessDenied → 403 · regra de negócio → 422
 *   payload inválido (MapRequestPayload) → 422 com os campos · HttpException → o status dela
 *   (401/403 do Security com mensagem própria em português).
 */
#[AsEventListener]
final class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $e = $event->getThrowable();

        [$status, $body] = match (true) {
            $e instanceof InvalidCredentials => [401, ['error' => $e->getMessage()]],
            $e instanceof AccessDenied => [403, ['error' => $e->getMessage()]],
            $e instanceof DomainException, $e instanceof \InvalidArgumentException => [422, ['error' => $e->getMessage()]],
            $e instanceof HttpExceptionInterface && $e->getPrevious() instanceof ValidationFailedException => [422, [
                'error' => 'Dados inválidos.',
                'fields' => self::violations($e->getPrevious()),
            ]],
            $e instanceof HttpExceptionInterface => [$e->getStatusCode(), ['error' => match ($e->getStatusCode()) {
                401 => 'Autenticação necessária.',
                403 => 'Acesso negado.',
                default => $e->getMessage(),
            }]],
            default => [null, null],
        };

        if (null !== $status) {
            $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];
            $event->setResponse(new JsonResponse($body, $status, $headers));
        }
    }

    /** @return array<string, string> */
    private static function violations(ValidationFailedException $e): array
    {
        $fields = [];
        foreach ($e->getViolations() as $v) {
            $fields[$v->getPropertyPath()] = (string) $v->getMessage();
        }

        return $fields;
    }
}
