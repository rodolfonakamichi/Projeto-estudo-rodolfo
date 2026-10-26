<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Shared\Money;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * Money ⇄ BIGINT em centavos (ADR: dinheiro nunca é float). A moeda não vai
 * para a coluna: o MVP é só BRL — gravar outra moeda é erro, não conversão.
 */
final class MoneyType extends Type
{
    private const CURRENCY = 'BRL';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getBigIntTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Money
    {
        return null === $value ? null : Money::fromCents((int) $value, self::CURRENCY);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        if (null === $value) {
            return null;
        }
        if (!$value instanceof Money || self::CURRENCY !== $value->currency()) {
            throw ValueNotConvertible::new($value, 'money');
        }

        return $value->cents();
    }
}
