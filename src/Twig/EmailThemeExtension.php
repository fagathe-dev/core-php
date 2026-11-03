<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Twig;

use Fagathe\CorePhp\Mailer\EmailTheme;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Expose les tokens des e-mails (globale `email_theme`) au layout et aux composants @CorePhp.
 *
 * Une globale reste lisible dans un include `with { … } only`, contrairement au contexte du template.
 */
final class EmailThemeExtension extends AbstractExtension implements GlobalsInterface
{
    public function getGlobals(): array
    {
        return ['email_theme' => EmailTheme::TOKENS];
    }
}
