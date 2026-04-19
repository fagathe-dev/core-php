<?php

namespace Fagathe\CorePhp\Mailer\Enum;

enum EmailTypeEnum: string
{
    case AUTH_CONFIRMATION = 'auth.confirmation';
    case AUTH_RESET_PASSWORD = 'auth.forgot_password.send_token';
    case AUTH_NOTIFICATION = 'notification';
    case ADMIN_USER_ACCOUNT_CREATED = 'admin_user_account_created';
    case INVOICE = 'invoice';
    case NEWSLETTER = 'newsletter';

    public static function all(): array
    {
        return [
            self::AUTH_CONFIRMATION,
            self::AUTH_RESET_PASSWORD,
            self::AUTH_NOTIFICATION,
            self::ADMIN_USER_ACCOUNT_CREATED,
            self::INVOICE,
            self::NEWSLETTER,
        ];
    }
}