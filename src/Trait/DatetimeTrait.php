<?php
namespace Fagathe\CorePhp\Trait;

use DateTime;
use DateTimeImmutable;
use Fagathe\CorePhp\Enum\HumanDueDateEnum;

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

    /**
     * Retourne une chaîne de temps relatif (ex: "il y a 2 jours", "dans 3 heures").
     * * @param DateTimeImmutable|null $dateTime
     * @return string|null
     */
    public function ago(?DateTimeImmutable $dateTime): ?string
    {
        if (!$dateTime) {
            return null;
        }

        $now = $this->now();
        $diff = $now->diff($dateTime);

        // Si la date est dans le futur (diff->invert === 0)
        if ($diff->invert === 0) {
            if ($diff->y > 0)
                return 'dans ' . $diff->y . ' an' . ($diff->y > 1 ? 's' : '');
            if ($diff->m > 0)
                return 'dans ' . $diff->m . ' mois';
            if ($diff->d > 0)
                return 'dans ' . $diff->d . ' jour' . ($diff->d > 1 ? 's' : '');
            if ($diff->h > 0)
                return 'dans ' . $diff->h . ' heure' . ($diff->h > 1 ? 's' : '');
            if ($diff->i > 0)
                return 'dans ' . $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
            return 'dans quelques secondes';
        }

        // Si la date est dans le passé (diff->invert === 1)
        if ($diff->y > 0)
            return 'il y a ' . $diff->y . ' an' . ($diff->y > 1 ? 's' : '');
        if ($diff->m > 0)
            return 'il y a ' . $diff->m . ' mois';
        if ($diff->d > 0)
            return 'il y a ' . $diff->d . ' jour' . ($diff->d > 1 ? 's' : '');
        if ($diff->h > 0)
            return 'il y a ' . $diff->h . ' heure' . ($diff->h > 1 ? 's' : '');
        if ($diff->i > 0)
            return 'il y a ' . $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');

        return 'à l\'instant';
    }

    /**
     * Formate une date d'échéance de manière lisible.
     *
     * @param DateTimeImmutable|null $dueDate
     * @return HumanDueDateEnum|null
     */
    public function formatHumanDueDate(?DateTimeImmutable $dueDate): ?HumanDueDateEnum
    {
        if (!$dueDate) {
            return null;
        }

        if ($this->isPastDate($dueDate)) {
            return HumanDueDateEnum::Overdue;
        }

        $now = $this->now();
        $nowMidnight = $now->setTime(0, 0, 0);
        $targetMidnight = $dueDate->setTime(0, 0, 0);
        
        $diff = $nowMidnight->diff($targetMidnight);
        $days = (int) $diff->format('%R%a');

        // Cas précis : Hier, Aujourd'hui, Demain
        if ($days === 0) return HumanDueDateEnum::Today;
        if ($days === 1) return HumanDueDateEnum::Tomorrow;

        // --- Logique des semaines ---
        // 'W' = Numéro de semaine, 'o' = Année ISO (indispensable pour les chevauchements décembre/janvier)
        $currentWeek = (int) $now->format('W');
        $currentYear = (int) $now->format('o');
        
        $targetWeek = (int) $dueDate->format('W');
        $targetYear = (int) $dueDate->format('o');

        // "Cette semaine" (Même année ISO, même semaine, et c'est dans le futur)
        if ($currentYear === $targetYear && $targetWeek === $currentWeek && $days > 1) {
            return HumanDueDateEnum::ThisWeek;
        }

        // "La semaine prochaine" (Semaine N+1, ou passage à la nouvelle année)
        $isNextWeek = ($currentYear === $targetYear && $targetWeek === $currentWeek + 1) ||
                      ($targetYear === $currentYear + 1 && $currentWeek >= 52 && $targetWeek === 1);

        if ($isNextWeek && $days > 1) {
            return HumanDueDateEnum::NextWeek;
        }

        // Fallback: format classique "ven. 10 juil."
        return HumanDueDateEnum::Later;
    }
}