<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Command;

/**
 * Pont entre la commande core-php:create-admin-user et le modèle utilisateur du projet hôte.
 *
 * core-php ne connaît ni l'entité User ni sa persistance : le projet hôte implémente cette
 * interface (une seule implémentation autowirée) et la commande l'utilise.
 */
interface AdminUserManagerInterface
{
    /**
     * Types d'administrateur proposés, le premier étant la valeur par défaut.
     *
     * @return array<string, string> type => rôle, ex. ['super_admin' => 'ROLE_SUPER_ADMIN', 'admin' => 'ROLE_ADMIN']
     */
    public function getAdminRoles(): array;

    public function usernameExists(string $username): bool;

    public function emailExists(string $email): bool;

    /**
     * Crée et enregistre l'administrateur : hachage du mot de passe, rôle, compte vérifié.
     * L'implémentation peut aussi notifier l'utilisateur (e-mail…).
     *
     * @throws \Throwable si la création échoue (validation, persistance…)
     */
    public function createAdmin(string $username, string $email, string $plainPassword, string $role): void;
}
