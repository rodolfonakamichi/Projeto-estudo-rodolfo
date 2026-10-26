<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * Base para VOs que são "uma string validada" (Slug, Email, RoleCode) ⇄ VARCHAR.
 * A subclasse diz o tamanho e como converter (Template Method).
 *
 * @template T of object
 */
abstract class StringValueType extends Type
{
    abstract protected function length(): int;

    /** @return T */
    abstract protected function fromString(string $value): object;

    /** @param T $value */
    abstract protected function toString(object $value): string;

    /** @return class-string<T> */
    abstract protected function valueClass(): string;

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => $this->length()]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?object
    {
        if (null === $value) {
            return null;
        }

        try {
            return $this->fromString((string) $value);
        } catch (\InvalidArgumentException $exception) {
            throw ValueNotConvertible::new($value, $this->valueClass(), $exception->getMessage(), $exception);
        }
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        $class = $this->valueClass();
        if ($value instanceof $class) {
            return $this->toString($value);
        }

        throw new \InvalidArgumentException(\sprintf('Esperado %s, recebido %s.', $class, get_debug_type($value)));
    }
}
