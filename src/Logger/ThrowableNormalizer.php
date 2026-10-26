<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Logger;

/**
 * Convertit une exception en tableau sérialisable pour les logs JSON (message, fichier, stack trace…).
 *
 * Les arguments des appels ne sont jamais conservés (données sensibles, volume) et les chemins
 * sont rendus relatifs à PROJECT_DIR quand c'est possible.
 */
final class ThrowableNormalizer
{
    /** Nombre maximum de frames conservées par exception. */
    public const MAX_FRAMES = 50;

    /** Profondeur maximale de la chaîne getPrevious(). */
    public const MAX_PREVIOUS = 5;

    /**
     * @return array{
     *     class: string,
     *     message: string,
     *     code: int|string,
     *     file: string,
     *     line: int,
     *     trace: list<array{file: string|null, line: int|null, class: string|null, type: string|null, function: string}>,
     *     truncated_frames?: int,
     *     previous?: array<string, mixed>
     * }
     */
    public static function normalize(\Throwable $throwable, int $depth = 0): array
    {
        $frames = $throwable->getTrace();
        $data = [
            'class' => $throwable::class,
            'message' => $throwable->getMessage(),
            'code' => $throwable->getCode(),
            'file' => self::relativePath($throwable->getFile()),
            'line' => $throwable->getLine(),
            'trace' => array_map(self::normalizeFrame(...), \array_slice($frames, 0, self::MAX_FRAMES)),
        ];

        if (\count($frames) > self::MAX_FRAMES) {
            $data['truncated_frames'] = \count($frames) - self::MAX_FRAMES;
        }

        $previous = $throwable->getPrevious();
        if ($previous !== null && $depth < self::MAX_PREVIOUS) {
            $data['previous'] = self::normalize($previous, $depth + 1);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $frame
     *
     * @return array{file: string|null, line: int|null, class: string|null, type: string|null, function: string}
     */
    private static function normalizeFrame(array $frame): array
    {
        return [
            'file' => isset($frame['file']) ? self::relativePath((string) $frame['file']) : null,
            'line' => isset($frame['line']) ? (int) $frame['line'] : null,
            'class' => $frame['class'] ?? null,
            'type' => $frame['type'] ?? null,
            'function' => (string) ($frame['function'] ?? ''),
        ];
    }

    private static function relativePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        if (\defined('PROJECT_DIR')) {
            $projectDir = rtrim(str_replace('\\', '/', (string) PROJECT_DIR), '/').'/';

            if (str_starts_with($path, $projectDir)) {
                return substr($path, \strlen($projectDir));
            }
        }

        return $path;
    }
}
