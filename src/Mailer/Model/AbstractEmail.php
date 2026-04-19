<?php

namespace Fagathe\CorePhp\Mailer\Model;

use Fagathe\CorePhp\Mailer\Enum\EmailTypeEnum;
use Fagathe\CorePhp\Mailer\Model\EmailInterface;

abstract class AbstractEmail implements EmailInterface
{
    protected array $context = [];
    protected array $toRecipients = [];
    protected array $ccRecipients = [];
    protected array $bccRecipients = [];
    protected ?array $from = null;

    public function __construct(protected EmailTypeEnum $type)
    {
    }

    public function getType(): EmailTypeEnum
    {
        return $this->type;
    }

    // -----------------------------
    // CONTEXT
    // -----------------------------
    public function setContext(array $values): self
    {
        $this->context = $values;
        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    // -----------------------------
    // FROM
    // -----------------------------
    public function from(string $email, ?string $name = null): self
    {
        $this->from = ['email' => $email, 'name' => $name];
        return $this;
    }

    public function getFrom(): ?array
    {
        return $this->from;
    }

    // -----------------------------
    // TO
    // -----------------------------
    public function to(string $email, ?string $name = null): self
    {
        $this->toRecipients[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    public function getTo(): array
    {
        return $this->toRecipients;
    }

    // -----------------------------
    // CC
    // -----------------------------
    public function cc(string $email, ?string $name = null): self
    {
        $this->ccRecipients[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    public function getCc(): array
    {
        return $this->ccRecipients;
    }

    // -----------------------------
    // BCC
    // -----------------------------
    public function bcc(string $email, ?string $name = null): self
    {
        $this->bccRecipients[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    public function getBcc(): array
    {
        return $this->bccRecipients;
    }
}
