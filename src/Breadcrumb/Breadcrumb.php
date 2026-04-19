<?php

namespace Fagathe\CorePhp\Breadcrumb;

class Breadcrumb
{
    public function __construct(
        /** * @var BreadcrumbItem[] $items 
         */
        private array $items = [],
        private bool $homePage = true,
        private ?array $options = []
    ) {
    }

    /**
     * @return BreadcrumbItem[] 
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function addItem(BreadcrumbItem $item): self
    {
        // On ajoute l'élément au début du tableau existant pour l'ordre, 
        // ou à la fin selon votre logique préférée. Ici array_merge([$item], ...) met au début.
        // Si vous voulez l'ajouter à la fin (ce qui est plus logique pour un fil d'ariane), faites :
        $this->items[] = $item;

        return $this;
    }

    public function prependItem(BreadcrumbItem $item): self
    {
        array_unshift($this->items, $item);
        return $this;
    }

    public function getHomePage(): bool
    {
        return $this->homePage;
    }

    public function getOptions(): ?array
    {
        return $this->options;
    }
}