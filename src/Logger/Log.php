<?php

namespace Fagathe\CorePhp\Logger;

use DateTimeImmutable;
use Fagathe\CorePhp\Enum\LoggerLevelEnum;

/**
 * Classe représentant une entrée de log.
 * * Contient toutes les informations nécessaires pour un log :
 * niveau, contenu, contexte, timestamp, etc.
 * * @author Journal App
 */
final class Log
{
    /**
     * Clés autorisées pour le contexte du log.
     */
    public const CONTEXT_KEYS = ['ip', 'device', 'browser', 'action', 'uid', 'user_agent', 'referer', 'url', 'method', 'origin'];

    /**
     * Clés autorisées pour le contenu du log.
     */
    public const CONTENT_KEYS = ['data', 'exception', 'message', 'response', 'request', 'sql'];

    /**
     * Liste des motifs de clés considérées comme sensibles.
     * Si une clé contient l'un de ces mots, sa valeur sera masquée.
     */
    private const SENSITIVE_PATTERNS = [
        'password',
        'pass',
        'pwd',
        'token',
        'secret',
        'credential',
        'iban',
        'rib',
        'card',
        'cvv',
        'api_key',
        'auth',
        'bearer'
    ];

    private string|int|null $id = null;
    private ?LoggerLevelEnum $level = null;
    private array $content = [];
    private array $context = [];
    private ?DateTimeImmutable $timestamp = null;

    public function __construct()
    {
        $this->timestamp = new DateTimeImmutable();
    }

    // ... [Getters/Setters ID et Level inchangés] ...

    public function getId(): string|int|null
    {
        return $this->id;
    }

    public function setId(string|int|null $id = null): self
    {
        $this->id = $id;
        return $this;
    }

    public function getLevel(): ?LoggerLevelEnum
    {
        return $this->level;
    }

    public function setLevel(string|LoggerLevelEnum $level): self
    {
        if (is_string($level)) {
            $level = LoggerLevelEnum::tryFrom($level);
        }
        $this->level = $level;
        return $this;
    }

    // ... [Content Methods Modified] ...

    public function getContent(string $key): mixed
    {
        return $this->content[$key] ?? null;
    }

    public function getContents(): ?array
    {
        return $this->content;
    }

    /**
     * Définit le contenu complet du log avec nettoyage des données sensibles.
     * * @param array<string, mixed> $content
     * @return self
     */
    public function setContent(array $content): self
    {
        // On nettoie l'ensemble du tableau
        $this->content = $this->sanitize($content);
        return $this;
    }

    /**
     * Ajoute un élément au contenu du log avec nettoyage.
     * * @param string $key Clé du contenu
     * @param mixed $value Valeur à ajouter
     * @return self
     */
    public function addContent(string $key, mixed $value): self
    {
        if (in_array($key, static::CONTENT_KEYS)) {
            // On nettoie la valeur spécifique en passant la clé pour vérification
            $this->content[$key] = $this->sanitize($value, $key);
        }
        return $this;
    }

    public function hasContent(string $key): bool
    {
        return array_key_exists($key, $this->content);
    }

    // ... [Context Methods Modified] ...

    public function getContext(string $key): mixed
    {
        return $this->context[$key] ?? null;
    }

    public function getContexts(): ?array
    {
        return $this->context;
    }

    /**
     * Définit le contexte complet du log avec nettoyage.
     * * @param array<string, string> $context
     * @return self
     */
    public function setContext(array $context): self
    {
        $this->context = $this->sanitize($context);
        return $this;
    }

    /**
     * Ajoute un élément au contexte du log avec nettoyage.
     * * @param string $key Clé du contexte
     * @param string $value Valeur à ajouter
     * @return self
     */
    public function addContext(string $key, string $value): self
    {
        if (in_array($key, static::CONTEXT_KEYS)) {
            $this->context[$key] = $this->sanitize($value, $key);
        }
        return $this;
    }

    public function hasContext(string $key): bool
    {
        return array_key_exists($key, $this->context);
    }

    // ... [Timestamp and Origin Methods unchanged] ...

    public function getTimestamp(): ?DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function setTimestamp(string|DateTimeImmutable $timestamp): self
    {
        if (is_string($timestamp)) {
            $timestamp = new DateTimeImmutable($timestamp);
        }
        $this->timestamp = $timestamp;
        return $this;
    }

    public function generate(): string
    {
        return (new LoggerTemplate($this))->generate();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'level' => $this->level?->value,
            'content' => $this->content,
            'context' => $this->context,
            'timestamp' => $this->timestamp?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Crée un log à partir d'un tableau.
     * Note : Les données sont nettoyées via les setters.
     */
    public static function fromArray(array $data): self
    {
        $log = new self();
        $log->setId($data['id'] ?? null);

        if (isset($data['level'])) {
            $log->setLevel($data['level']);
        }

        // Les setters appellent sanitize() automatiquement
        $log->setContent($data['content'] ?? []);
        $log->setContext($data['context'] ?? []);

        if (isset($data['timestamp'])) {
            $log->setTimestamp($data['timestamp']);
        }

        // Rétrocompatibilité : si origin est au niveau racine, le déplacer dans context
        if (isset($data['origin']) && !isset($data['context']['origin'])) {
            $log->addContext('origin', $data['origin']);
        }

        return $log;
    }

    /**
     * Nettoie récursivement les données sensibles.
     * * @param mixed $data La donnée à nettoyer (tableau, string, int, etc.)
     * @param string|null $keyName Le nom de la clé associée (pour vérification)
     * @return mixed La donnée nettoyée
     */
    private function sanitize(mixed $data, ?string $keyName = null): mixed
    {
        // 1. Si c'est un tableau, on récursive (parcours en profondeur)
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->sanitize($value, (string) $key);
            }
            return $data;
        }

        // 2. Si c'est une chaîne ou un nombre et qu'on a une clé sensible
        if ($keyName && (is_string($data) || is_numeric($data))) {
            if ($this->isSensitiveKey($keyName)) {
                $strValue = (string) $data;
                // Remplace chaque caractère par '*'
                return str_repeat('*', strlen($strValue));
            }
        }

        return $data;
    }

    /**
     * Vérifie si une clé correspond à un motif sensible.
     * * @param string $key
     * @return bool
     */
    private function isSensitiveKey(string $key): bool
    {
        $normalizedKey = strtolower($key);

        foreach (static::SENSITIVE_PATTERNS as $pattern) {
            // Vérifie si la clé contient le motif (ex: 'user_password' contient 'password')
            if (str_contains($normalizedKey, $pattern)) {
                return true;
            }
        }

        return false;
    }
}