<?php

declare(strict_types=1);

namespace App\Domain\Attendance;

/**
 * Data operacional (doc 03): o dia do bar não vira à meia-noite. Até a hora de
 * corte (05:00 no fuso da filial) ainda é o dia anterior — a comanda aberta à
 * 01:30 de sábado é do "dia" de sexta, e a numeração recomeça às 05:00.
 */
final class BusinessDay
{
    public const CUTOFF_HOUR = 5;

    /** @return \DateTimeImmutable a data (00:00, sem hora) no fuso da filial */
    public static function of(\DateTimeImmutable $instant, string $timezone, int $cutoffHour = self::CUTOFF_HOUR): \DateTimeImmutable
    {
        $local = $instant->setTimezone(new \DateTimeZone($timezone));
        if ((int) $local->format('G') < $cutoffHour) {
            $local = $local->modify('-1 day');
        }

        return new \DateTimeImmutable($local->format('Y-m-d'));
    }
}
