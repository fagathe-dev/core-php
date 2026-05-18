<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Enum;

enum HumanDueDateEnum: string
{
    case Overdue = 'overdue';
    case Today = 'today';
    case Tomorrow = 'tomorrow';
    case ThisWeek = 'this_week';
    case NextWeek = 'next_week';
    case Later = 'later';

        /**
     * Retourne la map des valeurs de l'enum ou une valeur spécifique si fournie
     * 
     * @param self|string|null $enum Valeur de l'enum ou string à convertir (optionnel)
     * @return array|string|null La map complète ou la valeur correspondante à l'enum fourni, ou null si non trouvé
     */
    public static function getMap(self|string|null $enum = null): array|string|null
    {
        $options = [
            self::Overdue->value => 'En retard',
            self::Today->value => 'Aujourd\'hui',
            self::Tomorrow->value => 'Demain',
            self::ThisWeek->value => 'Cette semaine',
            self::NextWeek->value => 'La semaine prochaine',
            self::Later->value => 'Plus tard',
        ];

        if (!is_null($enum)) {
            if (is_string($enum)) {
                $enum = self::tryFrom($enum);
            }

            return $options[$enum->value] ?? null;
        }


        return $options;
    }

    public static function label(self $enum): string
    {
        return self::getMap($enum) ?? $enum->value;
    }

    /**
     * @return array<string, string>
     */
    public static function choices(): array
    {
        return array_reduce(static::cases(), fn($carry, $i) => [...$carry, $i->value => self::getMap($i)[$i->value] ?? null] ?? null, []);
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_reduce(static::cases(), fn($carry, $i) => [...$carry, $i->value], []);
    }

}