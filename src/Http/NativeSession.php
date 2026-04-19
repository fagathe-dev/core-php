<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Http;

/**
 * Helper statique pour la gestion des sessions PHP natives (wrapper autour de $_SESSION).
 * * Attention : Dans un contexte Symfony standard, la session est souvent gérée 
 * par le framework. Cette classe manipule directement la superglobale $_SESSION.
 */
class NativeSession
{

    private const NATIVE_SESSION_KEY_PREFIX = '__ffr.v_';

    /**
     * S'assure que la session est démarrée.
     * Si une session est déjà active (via Symfony ou PHP natif), on ne fait rien.
     */
    private static function ensureStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Récupère une valeur en session.
     *
     * @param string $key La clé de la donnée.
     * @param mixed $defaultValue La valeur à retourner si la clé n'existe pas.
     * @return mixed
     */
    public static function get(string $key, mixed $defaultValue = null): mixed
    {
        self::ensureStarted();

        return $_SESSION[self::NATIVE_SESSION_KEY_PREFIX . $key] ?? $defaultValue;
    }

    /**
     * Récupère toutes les données de la session.
     *
     * @return array<string, mixed>
     */
    public static function getAll(): array
    {
        self::ensureStarted();

        return $_SESSION;
    }

    /**
     * Vérifie si une clé existe en session.
     *
     * @param string $key La clé à vérifier.
     * @return bool
     */
    public static function has(string $key): bool
    {
        self::ensureStarted();

        return array_key_exists(self::NATIVE_SESSION_KEY_PREFIX . $key, $_SESSION);
    }

    /**
     * Définit ou met à jour une valeur en session.
     *
     * @param string $key La clé.
     * @param mixed $value La valeur (peut être string, array, int, object, etc.).
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        self::ensureStarted();

        $_SESSION[self::NATIVE_SESSION_KEY_PREFIX . $key] = $value;
    }

    /**
     * Supprime une entrée spécifique de la session.
     *
     * @param string $key La clé à supprimer.
     * @return void
     */
    public static function delete(string $key): void
    {
        self::ensureStarted();

        if (array_key_exists(self::NATIVE_SESSION_KEY_PREFIX . $key, $_SESSION)) {
            unset($_SESSION[self::NATIVE_SESSION_KEY_PREFIX . $key]);
        }
    }

    /**
     * Vide complètement les données de la session courante.
     * Note: Cela ne détruit pas le fichier de session ni le cookie de session,
     * cela vide simplement le tableau $_SESSION.
     *
     * @return void
     */
    public static function clear(): void
    {
        self::ensureStarted();

        $sessionVariables = self::getAll();
        foreach ($sessionVariables as $key => $value) {
            if (str_starts_with($key, self::NATIVE_SESSION_KEY_PREFIX)) {
                unset($_SESSION[$key]);
            }
        }
    }

    /**
     * Détruit complètement la session (fichier et cookie).
     * À utiliser lors d'une déconnexion par exemple.
     *
     * @return bool Vrai si la destruction a réussi.
     */
    public static function destroy(): bool
    {
        self::ensureStarted();

        // 1. Vider le tableau
        $_SESSION = [];

        // 2. Supprimer le cookie de session si utilisé
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        // 3. Détruire la session
        return session_destroy();
    }

    /**
     * Récupère une valeur puis la supprime immédiatement (Flash data).
     *
     * @param string $key La clé.
     * @param mixed $defaultValue Valeur par défaut.
     * @return mixed
     */
    public static function flash(string $key, mixed $defaultValue = null): mixed
    {
        $value = self::get($key, $defaultValue);
        self::delete($key);

        return $value;
    }
}