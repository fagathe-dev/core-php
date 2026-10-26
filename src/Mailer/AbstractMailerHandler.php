<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer;

use Fagathe\CorePhp\Enum\LoggerLevelEnum;
use Fagathe\CorePhp\Mailer\Exception\EmailSendingException;
use Fagathe\CorePhp\Mailer\Exception\InvalidEmailDataException;
use Fagathe\CorePhp\Mailer\Exception\RecipientUnreachableException;
use Fagathe\CorePhp\Trait\LoggerTrait;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Base d'un handler d'e-mail : une classe par e-mail, déclarée dans le projet hôte.
 *
 * Le handler décrit l'e-mail (template, objet, destinataires, contexte) ; la base gère
 * l'envoi : validation du DTO, contrôle des destinataires, expéditeur par défaut,
 * contexte commun du layout, logs et exceptions (MailerExceptionInterface).
 *
 * Les dépendances sont injectées par setMailerDependencies() (#[Required]) : le
 * constructeur reste libre pour les dépendances propres au handler.
 *
 * @template T of object
 */
abstract class AbstractMailerHandler
{
    use LoggerTrait;

    protected ?Security $security = null;

    private MailerInterface $mailer;
    private ?ValidatorInterface $validator = null;
    private EmailDeliverabilityChecker $deliverabilityChecker;

    /** @var array<string, string|null> */
    private array $defaultContext = [];
    private ?Address $defaultFrom = null;

    #[Required]
    public function setMailerDependencies(
        MailerInterface $mailer,
        EmailDeliverabilityChecker $deliverabilityChecker,
        ?ValidatorInterface $validator = null,
        ?Security $security = null,
        #[Autowire(param: 'core_php.email.app_name')]
        ?string $appName = null,
        #[Autowire(param: 'core_php.email.app_uri')]
        ?string $appUri = null,
        #[Autowire(param: 'core_php.email.default_sender')]
        ?string $senderEmail = null,
        #[Autowire(param: 'core_php.email.logo_path')]
        ?string $logoPath = null,
        #[Autowire(param: 'core_php.email.support_email')]
        ?string $supportEmail = null,
    ): void {
        $this->mailer = $mailer;
        $this->deliverabilityChecker = $deliverabilityChecker;
        $this->validator = $validator;
        $this->security = $security;

        $this->defaultContext = [
            'app_name' => $appName ?: null,
            'logo_url' => self::resolveLogoUrl($logoPath, $appUri),
            'support_email' => $supportEmail ?: null,
        ];
        $this->defaultFrom = $senderEmail ? new Address($senderEmail, (string) $appName) : null;
    }

    /**
     * Template Twig du corps HTML, ex. 'emails/contact/recap.html.twig'.
     */
    abstract protected function getTemplate(): string;

    /**
     * @param T $dto
     */
    abstract protected function getSubject(object $dto): string;

    /**
     * @param T $dto
     *
     * @return list<Address|string>
     */
    abstract protected function getRecipients(object $dto): array;

    /**
     * Variables du template, fusionnées avec le contexte commun (app_name, logo_url, support_email).
     *
     * Ne pas utiliser la clé "email" : Symfony la réserve à l'objet e-mail dans les templates.
     *
     * @param T $dto
     *
     * @return array<string, mixed>
     */
    protected function getContext(object $dto): array
    {
        return ['dto' => $dto];
    }

    /**
     * @param T $dto
     */
    protected function getReplyTo(object $dto): ?Address
    {
        return null;
    }

    /**
     * Expéditeur : core_php.email.default_sender au nom de core_php.email.app_name par défaut.
     *
     * @param T $dto
     */
    protected function getFrom(object $dto): ?Address
    {
        return $this->defaultFrom;
    }

    /**
     * Valide le DTO et construit l'e-mail sans l'envoyer (prévisualisation, tests).
     *
     * @param T $dto
     *
     * @throws InvalidEmailDataException
     */
    public function createEmail(object $dto): TemplatedEmail
    {
        $this->validate($dto);

        $email = (new TemplatedEmail())
            ->subject($this->getSubject($dto))
            ->to(...$this->getRecipients($dto))
            ->htmlTemplate($this->getTemplate())
            ->context(array_merge($this->defaultContext, $this->getContext($dto)));

        if (null !== $from = $this->getFrom($dto)) {
            $email->from($from);
        }

        if (null !== $replyTo = $this->getReplyTo($dto)) {
            $email->replyTo($replyTo);
        }

        return $email;
    }

    /**
     * @param T $dto
     *
     * @throws InvalidEmailDataException     DTO invalide
     * @throws RecipientUnreachableException domaine du destinataire injoignable
     * @throws EmailSendingException         échec du transport (voir isRetryable())
     */
    public function send(object $dto): void
    {
        $email = $this->createEmail($dto);
        $recipients = array_map(static fn (Address $address): string => $address->getAddress(), $email->getTo());

        foreach ($recipients as $recipient) {
            if (!$this->deliverabilityChecker->isDeliverable($recipient)) {
                $this->log(LoggerLevelEnum::Error, 'Destinataire injoignable, e-mail non envoyé.', 'mailer.recipient_unreachable', ['recipient' => $recipient]);

                throw new RecipientUnreachableException($recipient);
            }
        }

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->log(LoggerLevelEnum::Error, \sprintf('Échec de l\'envoi de l\'e-mail "%s".', $email->getSubject()), 'mailer.send_error', ['recipients' => $recipients], $e);

            throw $e instanceof TransportExceptionInterface ? EmailSendingException::fromTransportException($e) : $e;
        }

        $this->log(LoggerLevelEnum::Info, \sprintf('E-mail "%s" envoyé.', $email->getSubject()), 'mailer.send_success', ['recipients' => $recipients]);
    }

    /**
     * @param T $dto
     */
    private function validate(object $dto): void
    {
        $violations = $this->validator?->validate($dto);

        if (null === $violations || 0 === \count($violations)) {
            return;
        }

        $exception = InvalidEmailDataException::fromViolations($dto::class, $violations);
        $this->log(LoggerLevelEnum::Warning, 'Données d\'e-mail invalides, e-mail non envoyé.', 'mailer.validation_failed', ['violations' => $exception->getViolations()]);

        throw $exception;
    }

    /**
     * Les logs ne contiennent jamais le contexte du template (jetons, mots de passe…).
     *
     * @param array<string, mixed> $data
     */
    private function log(LoggerLevelEnum $level, string $message, string $action, array $data, ?\Throwable $e = null): void
    {
        $content = [
            'message' => $message,
            'data' => ['handler' => static::class, 'template' => $this->getTemplate()] + $data,
        ];

        if (null !== $e) {
            $content['exception'] = $e->getMessage();
        }

        $this->generateLog($level, $content, ['action' => $action, 'uid' => $this->defaultContext['app_name'] ?? null]);
    }

    private static function resolveLogoUrl(?string $logoPath, ?string $appUri): ?string
    {
        if (!$logoPath) {
            return null;
        }

        if (preg_match('#^https?://#i', $logoPath)) {
            return $logoPath;
        }

        // Un e-mail ne peut pas résoudre un chemin relatif : sans URL de l'application, pas de logo.
        return $appUri ? rtrim($appUri, '/').'/'.ltrim($logoPath, '/') : null;
    }
}
