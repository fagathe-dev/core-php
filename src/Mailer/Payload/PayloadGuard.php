<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer\Payload;

use Fagathe\CorePhp\Mailer\Exception\InvalidPayloadException;

/**
 * Contrôles partagés par les payloads, pour échouer côté client plutôt que
 * recevoir un 422 / 500 de l'API.
 *
 * @internal
 */
final class PayloadGuard
{
    public static function notBlank(string $value, string $field): void
    {
        if ('' === trim($value)) {
            throw InvalidPayloadException::blankField($field);
        }
    }

    public static function httpsUrl(string $value, string $field): void
    {
        if (!str_starts_with($value, 'https://')) {
            throw InvalidPayloadException::insecureUrl($field, $value);
        }
    }

    public static function length(string $value, string $field, int $min, int $max): void
    {
        $length = mb_strlen($value);

        if ($length < $min || $length > $max) {
            throw InvalidPayloadException::invalidLength($field, $min, $max, $length);
        }
    }

    /**
     * Retire les champs optionnels non renseignés.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function withoutNulls(array $data): array
    {
        return array_filter($data, static fn (mixed $value): bool => null !== $value);
    }
}
