<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Shared\Identifier;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * Converte um ID tipado do Domain (CompanyId, UserId...) ⇄ CHAR(26) (ADR-005).
 * Cada ID ganha uma subclasse de 3 linhas que só diz qual classe é
 * (Template Method: o algoritmo fica aqui, o detalhe na subclasse).
 */
abstract class IdentifierType extends Type
{
    /** @return class-string<Identifier> */
    abstract protected function identifierClass(): string;

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 26, 'fixed' => true]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Identifier
    {
        if (null === $value || $value instanceof Identifier) {
            return $value;
        }

        try {
            return $this->identifierClass()::fromString((string) $value);
        } catch (\InvalidArgumentException $e) {
            throw ValueNotConvertible::new($value, $this->identifierClass(), $e->getMessage(), $e);
        }
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof Identifier) {
            return $value->toString();
        }

        throw new \InvalidArgumentException(\sprintf('Esperado %s, recebido %s.', $this->identifierClass(), get_debug_type($value)));
    }
}
