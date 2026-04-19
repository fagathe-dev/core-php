<?php
namespace Fagathe\CorePhp\Trait;

use DateTime;
use DateTimeImmutable;

trait DatetimeTrait
{

    public function createFromMutable(DateTime $datetime): DateTimeImmutable
    {
        return DateTimeImmutable::createFromMutable($datetime);
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', timezone: new \DateTimeZone('Europe/Paris'));
    }

    /**
     * formatDateTime
     *
     * @param  mixed $format
     * @param  mixed $dateTime
     * @return string
     */
    public function formatDateTime(?string $format = 'Y-m-d H:i:s', ?DateTimeImmutable $dateTime = null): string
    {
        return ($dateTime ?? $this->now())->format($format);
    }

    public function modifyDateTime(string $format, ?DateTimeImmutable $dateTime = null): DateTimeImmutable
    {
        $dateTime = $dateTime ?? $this->now();
        return $dateTime->modify($format);
    }

    /**
     * Vérifie si la première date est plus récente que la seconde.
     * 
     * @param DateTimeImmutable $date1 Première date à comparer
     * @param DateTimeImmutable $date2 Seconde date à comparer
     * @return bool True si $date1 est plus récente que $date2
     */
    public function isNewerThan(DateTimeImmutable $date1, DateTimeImmutable $date2): bool
    {
        return $date1 > $date2;
    }

    /**
     * Vérifie si une date est dans le passé.
     * 
     * @param DateTimeImmutable $date Date à vérifier
     * @param DateTimeImmutable|null $compareWith Date de comparaison (par défaut: maintenant)
     * @return bool True si $date est dans le passé par rapport à $compareWith
     */
    public function isPastDate(DateTimeImmutable $date, ?DateTimeImmutable $compareWith = null): bool
    {
        $compareWith = $compareWith ?? $this->now();
        return $date < $compareWith;
    }
}