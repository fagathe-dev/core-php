<?php

namespace Fagathe\CorePhp\Mailer\Enum;

enum RecipientTypeEnum: string
{
    case To = 'to';
    case Cc = 'cc';
    case Bcc = 'bcc';
}
