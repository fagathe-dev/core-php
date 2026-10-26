<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Command;

use Fagathe\CorePhp\Enum\LoggerLevelEnum;
use Fagathe\CorePhp\Trait\LoggerTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Création d'un administrateur depuis la ligne de commande.
 *
 * La saisie et la validation sont gérées ici ; la création (entité, hachage, persistance,
 * notification) est déléguée à l'implémentation de AdminUserManagerInterface du projet hôte.
 */
#[AsCommand(
    name: 'core-php:create-admin-user',
    description: 'Créer un utilisateur administrateur',
)]
final class CreateAdminUserCommand extends Command
{
    use LoggerTrait;

    private const LOG_FILE = 'command/create-admin-user';
    private const MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        private readonly ?AdminUserManagerInterface $userManager = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::OPTIONAL, 'Nom d\'utilisateur de l\'administrateur')
            ->addArgument('email', InputArgument::OPTIONAL, 'Adresse e-mail de l\'administrateur')
            ->addArgument('password', InputArgument::OPTIONAL, 'Mot de passe (saisie masquée si omis)')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Type d\'administrateur (voir AdminUserManagerInterface::getAdminRoles())')
            ->setHelp(
                <<<'HELP'
Crée un utilisateur administrateur vérifié.

  # Mode interactif (recommandé : mot de passe saisi de façon masquée)
  php bin/console core-php:create-admin-user

  # Mode non interactif
  php bin/console core-php:create-admin-user john john@example.com 'MotDePasse123' --type=admin

Le projet doit fournir une implémentation de Fagathe\CorePhp\Command\AdminUserManagerInterface.
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->userManager === null) {
            $io->error(sprintf('Aucune implémentation de %s n\'est enregistrée dans le projet.', AdminUserManagerInterface::class));

            return Command::FAILURE;
        }

        $roles = $this->userManager->getAdminRoles();
        if ($roles === []) {
            $io->error('Aucun type d\'administrateur n\'est configuré (getAdminRoles() est vide).');

            return Command::FAILURE;
        }

        $type = $this->resolveType($input, $io, $roles);
        $username = trim((string) ($input->getArgument('username') ?? $io->ask('Nom d\'utilisateur', null, $this->notBlank('Le nom d\'utilisateur'))));
        $email = trim((string) ($input->getArgument('email') ?? $io->ask('Adresse e-mail', null, $this->validEmail(...))));
        $password = (string) ($input->getArgument('password') ?? $io->askHidden('Mot de passe', $this->validPassword(...)));

        $errors = $this->validate($username, $email, $password);
        if ($errors !== []) {
            $io->error($errors);
            $this->log(LoggerLevelEnum::Warning, 'Validation échouée lors de la création d\'un administrateur', 'validation_failed', ['errors' => $errors]);

            return Command::FAILURE;
        }

        if ($this->userManager->usernameExists($username)) {
            $io->error(sprintf('Un utilisateur avec le nom "%s" existe déjà.', $username));
            $this->log(LoggerLevelEnum::Warning, 'Nom d\'utilisateur déjà utilisé', 'duplicate_username', ['username' => $username]);

            return Command::FAILURE;
        }

        if ($this->userManager->emailExists($email)) {
            $io->error(sprintf('Un utilisateur avec l\'adresse "%s" existe déjà.', $email));
            $this->log(LoggerLevelEnum::Warning, 'Adresse e-mail déjà utilisée', 'duplicate_email', ['email' => $email]);

            return Command::FAILURE;
        }

        $role = $roles[$type];

        try {
            $this->userManager->createAdmin($username, $email, $password, $role);
        } catch (\Throwable $e) {
            $io->error('Erreur lors de la création de l\'administrateur : ' . $e->getMessage());
            $this->log(LoggerLevelEnum::Error, 'Erreur lors de la création d\'un administrateur', 'error', ['username' => $username], $e);

            return Command::FAILURE;
        }

        $io->success(sprintf("Administrateur \"%s\" créé avec succès.\nRôle : %s\nE-mail : %s", $username, $role, $email));
        $this->log(LoggerLevelEnum::Info, 'Administrateur créé via la commande', 'success', ['username' => $username, 'email' => $email, 'role' => $role]);

        return Command::SUCCESS;
    }

    /**
     * @param array<string, string> $roles
     */
    private function resolveType(InputInterface $input, SymfonyStyle $io, array $roles): string
    {
        $type = $input->getOption('type');

        if (is_string($type) && isset($roles[$type])) {
            return $type;
        }

        if ($type !== null) {
            $io->warning(sprintf('Type invalide "%s". Types valides : %s', $type, implode(', ', array_keys($roles))));
        }

        // Non interactif : type par défaut (premier de la liste)
        return (string) $io->choice('Type d\'administrateur', array_keys($roles), array_key_first($roles));
    }

    /**
     * @return list<string>
     */
    private function validate(string $username, string $email, string $password): array
    {
        $errors = [];

        if ($username === '') {
            $errors[] = 'Le nom d\'utilisateur ne peut pas être vide.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L\'adresse e-mail n\'est pas valide.';
        }

        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors[] = sprintf('Le mot de passe doit contenir au moins %d caractères.', self::MIN_PASSWORD_LENGTH);
        }

        return $errors;
    }

    private function notBlank(string $label): \Closure
    {
        return static function (?string $value) use ($label): string {
            if (trim((string) $value) === '') {
                throw new \RuntimeException($label . ' ne peut pas être vide.');
            }

            return (string) $value;
        };
    }

    private function validEmail(?string $value): string
    {
        if (!filter_var(trim((string) $value), FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('L\'adresse e-mail n\'est pas valide.');
        }

        return (string) $value;
    }

    private function validPassword(?string $value): string
    {
        if (mb_strlen((string) $value) < self::MIN_PASSWORD_LENGTH) {
            throw new \RuntimeException(sprintf('Le mot de passe doit contenir au moins %d caractères.', self::MIN_PASSWORD_LENGTH));
        }

        return (string) $value;
    }

    /**
     * Les mots de passe ne sont jamais journalisés.
     *
     * @param array<string, mixed> $data
     */
    private function log(LoggerLevelEnum $level, string $message, string $action, array $data = [], ?\Throwable $e = null): void
    {
        $content = ['message' => $message, 'data' => $data];
        if ($e !== null) {
            $content['exception'] = $e;
        }

        $this->generateLog($level, $content, ['action' => 'command.create_admin_user.' . $action], self::LOG_FILE);
    }
}
