<?php

namespace Fagathe\CorePhp\Breadcrumb;

final class BreadcrumbItem
{
    public function __construct(
        private string $name = '',
        private ?string $link = null,
        private ?string $icon = null
    ) {
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }
}