<?php

namespace Fagathe\CorePhp\Twig;

use Fagathe\CorePhp\Breadcrumb\Breadcrumb;
use Fagathe\CorePhp\Breadcrumb\BreadcrumbGenerator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class BreadcrumbExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('generate_breadcrumb', [$this, 'generateBreadcrumb'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Génère le fil d'ariane HTML
     */
    public function generateBreadcrumb(?Breadcrumb $breadcrumb): string
    {
        if (null === $breadcrumb) {
            return '';
        }

        return (new BreadcrumbGenerator($breadcrumb))->generate();
    }
}