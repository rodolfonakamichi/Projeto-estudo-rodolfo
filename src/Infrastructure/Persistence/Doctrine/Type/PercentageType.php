<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Shared\Percentage;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/** Percentage ⇄ DECIMAL(5,2). O banco devolve DECIMAL como string ("12.50"): nada de float. */
final class PercentageType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getDecimalTypeDeclarationSQL(['precision' => 5, 'scale' => 2]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Percentage
    {
        return null === $value ? null : Percentage::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value instanceof Percentage ? $value->toString() : null;
    }
}
