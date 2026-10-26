<?php

declare(strict_types=1);

namespace App\Domain\Attendance;

use App\Domain\Shared\DomainException;

/**
 * Vínculo da comanda (VO do doc 02): tipo + referência, validados juntos.
 *   MESA            → ref = ID da Location (existência/tipo checados no caso de uso)
 *   BALCAO, AVULSO  → ref opcional (texto livre: "João", "viagem")
 *   PULSEIRA, EVENTO, VEICULO, SERVICO → ref obrigatória (código, placa...)
 *   CLIENTE, QUARTO → ainda não: dependem do cadastro de clientes / do módulo hotel.
 */
final readonly class BindTarget
{
    private const MAX_REF = 60;

    private function __construct(
        public BindType $type,
        public ?string $ref,
    ) {
    }

    public static function of(BindType $type, ?string $ref): self
    {
        $ref = null === $ref ? null : trim($ref);
        $ref = '' === $ref ? null : $ref;

        if (null !== $ref && mb_strlen($ref) > self::MAX_REF) {
            throw new DomainException(\sprintf('A referência do vínculo deve ter no máximo %d caracteres.', self::MAX_REF));
        }

        return match ($type) {
            BindType::Table => new self($type, LocationId::fromString($ref ?? throw new DomainException('Comanda de mesa precisa da mesa.'))->toString()),
            BindType::Counter, BindType::Walkin => new self($type, $ref),
            BindType::Wristband, BindType::Event, BindType::Vehicle, BindType::Service => new self($type, $ref ?? throw new DomainException("Vínculo {$type->value} precisa de uma referência.")),
            BindType::Customer, BindType::Room => throw new DomainException("Vínculo {$type->value} ainda não é suportado."),
        };
    }

    public function isTable(): bool
    {
        return BindType::Table === $this->type;
    }

    public function locationId(): ?LocationId
    {
        return $this->isTable() && null !== $this->ref ? LocationId::fromString($this->ref) : null;
    }
}
