<?php

namespace Fagathe\CorePhp\Mailer\Service;

use Fagathe\CorePhp\Mailer\Enum\EmailTypeEnum;
use Fagathe\CorePhp\Mailer\Model\Email;

final class EmailMockFactory
{
    public function create(EmailTypeEnum $type): Email
    {
        $preview = true;

        // Utilisateur fictif pour la prévisualisation (accessible via user.xxx dans Twig)
        $mockUser = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'username' => 'johndoe',
            'email' => 'demo@example.com',
        ];

        return match ($type) {

            EmailTypeEnum::AUTH_CONFIRMATION =>
            (new Email($type, 'Confirmation de compte'))
                ->from('no-reply@example.com', 'My App')
                ->to('demo@example.com', 'John Doe')
                ->setContext([
                    'user' => $mockUser,
                    'confirmationUrl' => 'https://example.com/register/confirm/abc123token',
                    'expires_in' => '24 heures',
                    ...compact('preview'),
                ]),

            EmailTypeEnum::AUTH_RESET_PASSWORD =>
            (new Email($type, 'Réinitialisation de mot de passe'))
                ->from('no-reply@example.com', 'My App')
                ->to('demo@example.com', 'John Doe')
                ->setContext([
                    'user' => $mockUser,
                    'reset_url' => 'https://example.com/auth/reset-password/abc123token',
                    'expires_in' => '1 heure',
                    ...compact('preview'),
                ]),

            EmailTypeEnum::INVOICE =>
            (new Email($type, 'Votre facture'))
                ->from('billing@example.com', 'Facturation')
                ->to('demo@example.com', 'John Doe')
                ->setContext([
                    'invoice_number' => 'FAC-2024-001',
                    'amount' => '149.99',
                    ...compact('preview'),
                ]),

            EmailTypeEnum::NEWSLETTER =>
            (new Email($type, 'Newsletter Mensuelle'))
                ->from('newsletter@example.com', 'Newsletter')
                ->to('demo@example.com', 'John Doe')
                ->setContext([
                    'month' => 'Juin 2024',
                    'articles' => [
                        ['title' => 'Nouveautés de Juin', 'summary' => 'Découvrez les dernières fonctionnalités...'],
                        ['title' => 'Conseils et Astuces', 'summary' => 'Améliorez votre expérience avec nos conseils...'],
                    ],
                    ...compact('preview'),
                ]),

            EmailTypeEnum::AUTH_NOTIFICATION =>
            (new Email($type, 'Mis à jour de votre compte'))
                ->from('notification@example.com', 'Notification')
                ->to('demo@example.com', 'John Doe')
                ->setContext([
                    'update_details' => 'Votre profil a été mis à jour avec succès le 15 Juin 2024.',
                    ...compact('preview'),
                ]),

            EmailTypeEnum::ADMIN_USER_ACCOUNT_CREATED =>
            (new Email($type, 'Bienvenue - Votre compte a été créé'))
                ->from('no-reply@example.com', 'My App')
                ->to('demo@example.com', 'John Doe')
                ->setContext([
                    'user' => $mockUser,
                    'username' => 'johndoe',
                    'mail' => 'demo@example.com',
                    'password' => 'Temp@Pass123!',
                    'roles' => 'Administrateur',
                    'loginUrl' => 'https://example.com/auth/login',
                    'changePasswordUrl' => 'https://example.com/auth/reset-password/abc123token',
                    'createdBy' => 'dashboard',
                    'createdAt' => new \DateTimeImmutable(),
                    ...compact('preview'),
                ]),

            default => throw new \RuntimeException('Type email non supporté : ' . $type->value),
        };
    }

    /**
     * Retourne tous les types d'email qui ont un mock disponible.
     *
     * @return EmailTypeEnum[]
     */
    public function getAvailableTypes(): array
    {
        return EmailTypeEnum::all();
    }
}
