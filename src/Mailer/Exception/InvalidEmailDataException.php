<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Le DTO passé au handler ne respecte pas ses contraintes de validation : aucun e-mail n'est envoyé.
 */
final class InvalidEmailDataException extends \InvalidArgumentException implements MailerExceptionInterface
{
    /**
     * @param array<string, string> $violations ['propriété' => 'message']
     */
    private function __construct(string $message, private readonly array $violations)
    {
        parent::__construct($message);
    }

    public static function fromViolations(string $dtoClass, ConstraintViolationListInterface $violationList): self
    {
        $violations = [];
        foreach ($violationList as $violation) {
            $violations[$violation->getPropertyPath()] = (string) $violation->getMessage();
        }

        return new self(
            \sprintf('Données d\'e-mail invalides (%s) : %s', $dtoClass, implode(' ', $violations)),
            $violations,
        );
    }

    /**
     * @return array<string, string> ['propriété' => 'message']
     */
    public function getViolations(): array
    {
        return $this->violations;
    }
}
