<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer\Exception;

/**
 * 422 : données refusées (voir getViolations()) ou destinataire injoignable
 * (getErrorCode() = "email_recipient_error" / "recipient_rejected").
 */
final class MailerValidationException extends MailerApiException
{
}
