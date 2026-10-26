<?php

declare(strict_types=1);

namespace App\Application\Audit;

use App\Application\Shared\Bus\EventHandler;
use App\Application\Shared\Event\RecordedEvent;
use App\Domain\Identity\UserId;
use App\Domain\Identity\UserNotFound;
use App\Domain\Identity\UserRepository;

/**
 * Consumidor do outbox (doc 05): transforma eventos de ações SENSÍVEIS em
 * linhas de auditoria legíveis (doc 01 §2.8: cancelamento, desconto,
 * reabertura...). Evento fora da lista é ignorado — lançar item não é auditado,
 * vai só para a timeline.
 *
 * O id da auditoria é o id do evento: reprocessar não duplica.
 */
final readonly class AuditTrail implements EventHandler
{
    public function __construct(
        private AuditLog $auditLog,
        private UserRepository $users,
    ) {
    }

    public function __invoke(RecordedEvent $event): void
    {
        $described = $this->describe($event->name, $event->payload);
        if (null === $described) {
            return;
        }

        [$action, $actorKey, $summary] = $described;
        $actorId = isset($event->payload[$actorKey]) ? (string) $event->payload[$actorKey] : null;

        $this->auditLog->record(new AuditEntry(
            id: $event->id,
            companyId: $event->companyId,
            branchId: $event->branchId,
            actorId: $actorId,
            actorName: $this->actorName($actorId),
            action: $action,
            targetType: $event->aggregateType,
            targetId: $event->aggregateId,
            summary: mb_substr($summary, 0, 255),
            metadata: $event->payload,
            createdAt: $event->occurredAt(),
        ));
    }

    /**
     * nome do evento => [ação (código do doc 06), chave do ator no payload, resumo].
     *
     * @param array<string, mixed> $payload
     *
     * @return array{string, string, string}|null
     */
    private function describe(string $name, array $payload): ?array
    {
        $number = $payload['displayNumber'] ?? '?';

        return match ($name) {
            'CommandOpened' => ['command.open', 'openedBy', "Comanda #{$number} aberta ({$payload['bindType']})"],
            'CommandItemChanged' => ['command.item.change', 'actorId', "Comanda #{$number}: {$payload['itemName']} de {$payload['oldQuantity']} para {$payload['newQuantity']}"],
            'CommandItemCancelled' => ['command.item.cancel', 'actorId', "Comanda #{$number}: {$payload['quantity']}× {$payload['itemName']} cancelado — {$payload['reason']}"],
            'CommandCancelled' => ['command.cancel', 'actorId', "Comanda #{$number} cancelada — {$payload['reason']}"],
            default => null,
        };
    }

    /** O nome vai copiado: a auditoria tem que ser legível mesmo se o usuário for renomeado/desativado. */
    private function actorName(?string $actorId): string
    {
        if (null === $actorId) {
            return 'sistema';
        }

        try {
            return $this->users->get(UserId::fromString($actorId))->name();
        } catch (UserNotFound) {
            return '(usuário removido)';
        }
    }
}
