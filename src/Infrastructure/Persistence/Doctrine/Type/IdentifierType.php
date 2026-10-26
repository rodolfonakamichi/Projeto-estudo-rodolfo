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
 *
 * Na ida para o banco aceita o objeto OU uma string que seja ULID válido: em
 * PK composta com associação (RolePermission, FavoriteProduct) o próprio
 * Doctrine "achata" o ID da associação para string no UPDATE/DELETE. Qualquer
 * outra coisa (string que não é ULID, objeto de outro tipo) continua erro.
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
        } catch (\InvalidArgumentException $exception) {
            throw ValueNotConvertible::new($value, $this->identifierClass(), $exception->getMessage(), $exception);
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
        if (\is_string($value)) {
            try {
                return $this->identifierClass()::fromString($value)->toString();
            } catch (\InvalidArgumentException $exception) {
                throw ValueNotConvertible::new($value, $this->identifierClass(), $exception->getMessage(), $exception);
            }
        }

        throw new \InvalidArgumentException(\sprintf('Esperado %s, recebido %s.', $this->identifierClass(), get_debug_type($value)));
    }
}
