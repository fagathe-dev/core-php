<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Http;

/**
 * Helper statique pour la gestion des cookies côté serveur (basé sur $_COOKIE et setcookie).
 */
class Cookie
{
    private const NATIVE_COOKIE_KEY_PREFIX = '__ffr.v_';

    /**
     * Récupère la valeur d'un cookie spécifique par son nom.
     *
     * @param string $key Le nom du cookie.
     * @param string|null $defaultValue La valeur à retourner si le cookie n'est pas trouvé.
     * @return string|null
     */
    public static function get(string $key, ?string $defaultValue = null): ?string
    {
        return $_COOKIE[self::NATIVE_COOKIE_KEY_PREFIX . $key] ?? $defaultValue;
    }

    /**
     * Récupère tous les cookies décodés.
     *
     * @return array<string, string>
     */
    public static function getAll(): array
    {
        // $_COOKIE décode automatiquement les valeurs, nous retournons l'intégralité
        return $_COOKIE;
    }

    /**
     * Vérifie l'existence d'un cookie.
     *
     * @param string $key La clé du cookie.
     * @return bool
     */
    public static function has(string $key): bool
    {
        return isset($_COOKIE[self::NATIVE_COOKIE_KEY_PREFIX . $key]);
    }

    /**
     * Définit (ajoute ou met à jour) un cookie.
     *
     * @param string $key Le nom du cookie.
     * @param string $value La valeur du cookie.
     * @param array<string, mixed> $options Les options pour setcookie().
     * Options par défaut :
     * - expires: time() + (86400 * 30) (30 jours)
     * - path: '/'
     * - samesite: 'Lax'
     * - httponly: true
     * @return bool Vrai en cas de succès, Faux sinon.
     */
    public static function set(string $key, string $value, array $options = []): bool
    {
        // Options par défaut (utilisant l'approche "setcookie" avec le tableau d'options, PHP 7.3+)
        $defaultOptions = [
            'expires' => time() + (86400 * 30), // 30 jours par défaut
            'path' => '/',
            'samesite' => 'Lax',
            'httponly' => true,
            'secure' => false,
        ];

        $options = array_merge($defaultOptions, $options);

        // setcookie() doit être appelée avant tout envoi de contenu
        return setcookie(static::NATIVE_COOKIE_KEY_PREFIX . $key, $value, $options);
    }

    /**
     * Supprime un cookie en le faisant expirer immédiatement.
     *
     * @param string $key Le nom du cookie à supprimer.
     * @return bool Vrai en cas de succès, Faux sinon.
     */
    public static function delete(string $key): bool
    {
        // Pour supprimer un cookie, nous le redéfinissons avec une date d'expiration dans le passé.
        // Il est crucial d'utiliser les mêmes options (path, domain, secure) que celles utilisées lors de la création
        // pour que le navigateur identifie le bon cookie à supprimer. Nous utilisons ici les valeurs par défaut
        // pour 'path' et 'samesite' qui couvrent la plupart des cas.
        $options = [
            'expires' => time() - 3600, // Une heure dans le passé
            'path' => '/',
            'samesite' => 'Lax',
        ];

        // setcookie doit être appelée avant tout envoi de contenu
        // La valeur est vide.
        $result = setcookie(static::NATIVE_COOKIE_KEY_PREFIX . $key, '', $options);

        // Suppression de la superglobale pour la requête en cours
        if ($result && isset($_COOKIE[self::NATIVE_COOKIE_KEY_PREFIX . $key])) {
            unset($_COOKIE[self::NATIVE_COOKIE_KEY_PREFIX . $key]);
        }

        return $result;
    }

    /**
     * Supprime tous les cookies actuellement présents dans la superglobale $_COOKIE.
     *
     * @return bool Vrai si toutes les suppressions ont réussi, Faux sinon.
     */
    public static function clear(): bool
    {
        $success = true;
        foreach ($_COOKIE as $key => $value) {
            if (!self::delete(str_replace(self::NATIVE_COOKIE_KEY_PREFIX, '', $key))) {
                $success = false;
            }
        }

        return $success;
    }
}