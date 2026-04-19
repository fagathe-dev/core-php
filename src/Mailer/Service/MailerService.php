<?php

namespace Fagathe\CorePhp\Mailer\Service;

use Fagathe\CorePhp\Enum\LoggerLevelEnum;
use Fagathe\CorePhp\Trait\LoggerTrait;
use Fagathe\CorePhp\Mailer\Model\EmailInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class MailerService
{
    use LoggerTrait;
    private ?Request $request;

    /**
     * @param MailerInterface $mailer
     * @param string $defaultUri URL de base de l'application (depuis DEFAULT_URI)
     * @param array<string, mixed> $defaultContext Variables globales injectées dans tous les templates d'e-mail.
     */
    public function __construct(
        private MailerInterface $mailer,
        private Security $security,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(env: 'DEFAULT_URI')]
        private string $defaultUri,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(env: 'APP_NAME')]
        private string $appName,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(env: 'LOGO_PATH')]
        private string $logoPath,
    ) {
        $this->request = Request::createFromGlobals();
    }

    public function send(EmailInterface $email): void
    {
        try {
            $recipients = array_map(fn($r) => $r['email'], $email->getTo());

            $this->generateLog(
                LoggerLevelEnum::Info,
                [
                    'message' => 'Préparation envoi email',
                    'subject' => $email->getSubject(),
                    'template' => $email->getTemplate(),
                    'recipients' => implode(', ', $recipients)
                ],
                ['action' => 'mailer.send.prepare']
            );

            $twigEmail = new TemplatedEmail();

            // FROM
            if ($email->getFrom()) {
                $from = $email->getFrom();
                $twigEmail->from(new Address($from['email'], $from['name'] ?? ''));
            }

            // TO
            foreach ($email->getTo() as $recipient) {
                $twigEmail->addTo(new Address($recipient['email'], $recipient['name'] ?? ''));
            }

            // CC
            foreach ($email->getCc() as $recipient) {
                $twigEmail->addCc(new Address($recipient['email'], $recipient['name'] ?? ''));
            }

            // BCC
            foreach ($email->getBcc() as $recipient) {
                $twigEmail->addBcc(new Address($recipient['email'], $recipient['name'] ?? ''));
            }

            // Fusion du contexte par défaut avec le contexte spécifique de l'email.
            // Les valeurs spécifiques de l'email ($email->getContext()) écrasent les valeurs par défaut en cas de conflit.
            $finalContext = [...$this->getDefaultContext(), ...$email->getContext()];

            // Sujet, template et contexte
            $twigEmail
                ->subject($email->getSubject())
                ->htmlTemplate($email->getTemplate())
                ->context($finalContext);

            // Envoi
            $this->mailer->send($twigEmail);

            $this->generateLog(
                LoggerLevelEnum::Info,
                [
                    'message' => 'Email envoyé avec succès',
                    'subject' => $email->getSubject(),
                    'recipients' => implode(', ', $recipients)
                ],
                ['action' => 'mailer.send.success']
            );

        } catch (TransportExceptionInterface $e) {
            $this->generateLog(
                LoggerLevelEnum::Error,
                [
                    'message' => 'Erreur transport lors de l\'envoi d\'email',
                    'subject' => $email->getSubject() ?? 'unknown',
                    'error' => $e->getMessage()
                ],
                ['action' => 'mailer.send.transport_error']
            );

            throw $e;

        } catch (\Throwable $e) {
            $this->generateLog(
                LoggerLevelEnum::Critical,
                [
                    'message' => 'Erreur critique lors de l\'envoi d\'email',
                    'subject' => $email->getSubject() ?? 'unknown',
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e)
                ],
                ['action' => 'mailer.send.critical_error']
            );

            throw $e;
        }
    }

    public function getDefaultContext(): array
    {
        // Utilisation de DEFAULT_URI et LOGO_PATH pour construire l'URL absolue du logo
        $baseUrl = rtrim($this->defaultUri, '/');

        return [
            'BASE_URL' => $baseUrl,
            'DOMAIN' => parse_url($baseUrl, PHP_URL_HOST) ?? '',
            'COMPANY_NAME' => $this->appName,
            'LOGO' => $baseUrl . $this->logoPath,
        ];
    }
}