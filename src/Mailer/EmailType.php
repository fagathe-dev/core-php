<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Mailer;

/**
 * Modèles d'e-mails acceptés par l'API Mailer (champ "type" de POST /api/emails/send).
 */
enum EmailType: string
{
    case AUTH_CONFIRMATION = 'auth_confirmation';
    case AUTH_OTP = 'auth_otp';
    case AUTH_RESET_PASSWORD = 'auth_reset_password';
    case AUTH_PROFILE_CHANGE_EMAIL = 'auth_profile_change_email';
    case ADMIN_ACCOUNT_CREATED = 'admin_account_created';
    case CONTACT_RECAP = 'contact_recap';
}
