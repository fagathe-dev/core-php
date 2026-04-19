<?php

namespace Fagathe\CorePhp\Mailer\Model;

use Fagathe\CorePhp\Mailer\Enum\EmailTypeEnum;

final class Email extends AbstractEmail
{
    private ?string $template = null;

    public function __construct(
        EmailTypeEnum $type,
        private string $subject
    ) {
        parent::__construct($type);
        $this->template = str_replace(['.', '_'], ['/', '-'], $type->value);
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getTemplate(): string
    {
        return 'emails/' . $this->template . '.html.twig';
    }
}
