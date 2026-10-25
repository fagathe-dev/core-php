<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer\Exception;

/**
 * 401 : jeton absent, mal copié ou révoqué. Ne pas réessayer.
 */
final class MailerAuthenticationException extends MailerApiException
{
}
