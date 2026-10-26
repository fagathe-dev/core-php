# Commandes console

Commandes Symfony fournies par core-php (`Fagathe\CorePhp\Command\*`), enregistrées automatiquement par `CorePhpBundle` (`config/my_commands.yaml`).

| Commande | Rôle |
| --- | --- |
| `core-php:log-file-rotation` | Supprime les logs plus anciens que la durée de rétention et les dossiers devenus vides |
| `core-php:create-admin-user` | Crée un administrateur vérifié (nécessite une implémentation de `AdminUserManagerInterface`) |

---

## core-php:log-file-rotation

Délègue à `JsonLoggerRotation::deleteOldFiles()` et affiche le rapport (date limite, fichiers et dossiers supprimés, erreurs).

- Répertoire : paramètre `core_php.logger.logs_dir` (`%kernel.project_dir%/logs/`)
- Rétention : paramètre `core_php.logger.retention_delay` (30 jours)

```bash
php bin/console core-php:log-file-rotation
```

À planifier quotidiennement, par exemple :

```cron
0 3 * * * cd /var/www/app && php bin/console core-php:log-file-rotation --no-interaction
```

---

## core-php:create-admin-user

core-php ne connaît pas l'entité `User` du projet : la commande gère la saisie et la validation, puis délègue
la création à **une** implémentation de `Fagathe\CorePhp\Command\AdminUserManagerInterface` fournie par le projet hôte
(autowirée automatiquement). Sans implémentation, la commande s'arrête avec un message explicite.

```php
use Fagathe\CorePhp\Command\AdminUserManagerInterface;

final class AdminUserManager implements AdminUserManagerInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    public function getAdminRoles(): array
    {
        // Le premier type est la valeur par défaut
        return ['super_admin' => 'ROLE_SUPER_ADMIN', 'admin' => 'ROLE_ADMIN'];
    }

    public function usernameExists(string $username): bool
    {
        return $this->userRepository->findOneBy(['username' => $username]) !== null;
    }

    public function emailExists(string $email): bool
    {
        return $this->userRepository->findOneBy(['email' => $email]) !== null;
    }

    public function createAdmin(string $username, string $email, string $plainPassword, string $role): void
    {
        $user = (new User())->setUsername($username)->setEmail($email)->setRoles([$role])->setIsVerified(true);
        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));

        $this->userRepository->save($user, flush: true, isCreation: true);
    }
}
```

```bash
# Mode interactif (mot de passe saisi de façon masquée)
php bin/console core-php:create-admin-user

# Mode non interactif
php bin/console core-php:create-admin-user john john@example.com 'MotDePasse123' --type=admin
```

- Validation : nom d'utilisateur non vide, e-mail valide, mot de passe de 8 caractères minimum, unicité du nom et de l'e-mail.
- Journalisation dans `logs/command/create-admin-user-<date>.json` (le mot de passe n'est jamais journalisé).
