<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeImmutableType;

/** Sobrescreve `datetime_immutable` (config/packages/doctrine.yaml). */
final class DateTimeImmutableMicrosecondsType extends DateTimeImmutableType
{
    use MicrosecondsDateTime;

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        if (\is_string($value)) {
            $date = \DateTimeImmutable::createFromFormat(self::FORMAT, $value, self::utc());
            if (false !== $date) {
                return $date;
            }
        }

        return parent::convertToPHPValue($value, $platform);
    }
}
