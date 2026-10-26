<?php

declare(strict_types=1);

namespace App\Presentation\Http\Error;

use App\Application\Shared\AccessDenied;
use App\Domain\Identity\InvalidCredentials;
use App\Domain\Shared\DomainException;
use App\Domain\Shared\NotFound;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Traduz exceções em JSON padronizado nas rotas /api — um lugar só, em vez de
 * try/catch em todo controller:
 *   InvalidCredentials → 401 · AccessDenied → 403 · NotFound → 404 · regra de negócio → 422
 *   UNIQUE violado no banco → 409 (corrida que passou pela checagem do caso de uso)
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

        $exception = $event->getThrowable();

        [$status, $body] = match (true) {
            $exception instanceof InvalidCredentials => [401, ['error' => $exception->getMessage()]],
            $exception instanceof AccessDenied => [403, ['error' => $exception->getMessage()]],
            $exception instanceof NotFound => [404, ['error' => $exception->getMessage()]],
            $exception instanceof UniqueConstraintViolationException => [409, ['error' => 'Conflito: outra operação acabou de gravar o mesmo dado. Atualize e tente de novo.']],
            $exception instanceof DomainException, $exception instanceof \InvalidArgumentException => [422, ['error' => $exception->getMessage()]],
            $exception instanceof HttpExceptionInterface && $exception->getPrevious() instanceof ValidationFailedException => [422, [
                'error' => 'Dados inválidos.',
                'fields' => self::violations($exception->getPrevious()),
            ]],
            $exception instanceof HttpExceptionInterface => [$exception->getStatusCode(), ['error' => match ($exception->getStatusCode()) {
                401 => 'Autenticação necessária.',
                403 => 'Acesso negado.',
                default => $exception->getMessage(),
            }]],
            default => [null, null],
        };

        if (null !== $status) {
            $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
            $event->setResponse(new JsonResponse($body, $status, $headers));
        }
    }

    /** @return array<string, string> */
    private static function violations(ValidationFailedException $exception): array
    {
        $fields = [];
        foreach ($exception->getViolations() as $violation) {
            $fields[$violation->getPropertyPath()] = (string) $violation->getMessage();
        }

        return $fields;
    }
}
