<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Regra do projeto (doc 03): toda data é DATETIME(6), em UTC. O DBAL 4 só gera
 * DATETIME (segundos); este trait é usado pelos tipos que SOBRESCREVEM os
 * `datetime` e `datetime_immutable` nativos — assim vale para todas as
 * colunas, inclusive a tabela de controle do Doctrine Migrations.
 */
trait MicrosecondsDateTime
{
    private const FORMAT = 'Y-m-d H:i:s.u';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'DATETIME(6)';
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value)
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format(self::FORMAT);
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    private static function utc(): \DateTimeZone
    {
        return new \DateTimeZone('UTC');
    }
}
