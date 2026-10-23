<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeType;

/** Sobrescreve `datetime` (config/packages/doctrine.yaml). O Domain usa só o imutável. */
final class DateTimeMicrosecondsType extends DateTimeType
{
    use MicrosecondsDateTime;

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTime
    {
        if (\is_string($value)) {
            $date = \DateTime::createFromFormat(self::FORMAT, $value, self::utc());
            if (false !== $date) {
                return $date;
            }
        }

        return parent::convertToPHPValue($value, $platform);
    }
}
