<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer\Exception;

/**
 * Interface commune des exceptions du module Mailer : l'attraper pour gérer tous les échecs d'envoi.
 */
interface MailerExceptionInterface extends \Throwable
{
}
