<?php

namespace Fagathe\CorePhp\Breadcrumb;

use Fagathe\CorePhp\Http\RequestTrait;


class BreadcrumbGenerator
{
    use RequestTrait;


    private const DEFAULT_BASE_ROUTE = '/';
    private const ADMIN_BASE_ROUTE = '/admin';
    private const DASHBOARD_BASE_ROUTE = '/dashboard';

    public function __construct(
        private ?Breadcrumb $breadcrumb = null
    ) {
    }

    public function generate(): ?string
    {
        if (!$this->breadcrumb) {
            return null;
        }

        // Ajout automatique de l'accueil si demandé
        if ($this->breadcrumb->getHomePage()) {
            $route = match (true) {
                $this->isDashboard() => [
                    'path' => self::DASHBOARD_BASE_ROUTE,
                    'label' => 'Tableau de bord',
                ],
                $this->isAdmin() => [
                    'path' => self::ADMIN_BASE_ROUTE,
                    'label' => 'Administration',
                ],
                default => [
                    'path' => self::DEFAULT_BASE_ROUTE,
                    'label' => 'Accueil',
                ],
            };

            // On l'ajoute au début de la liste
            $this->breadcrumb->prependItem(new BreadcrumbItem($route['label'], $route['path']));
        }

        $html = '<nav aria-label="breadcrumb">';
        $html .= '<ol class="breadcrumb">';

        $items = $this->breadcrumb->getItems();
        $lastKey = array_key_last($items);

        foreach ($items as $key => $item) {
            $html .= $this->renderItem($item, $key === $lastKey);
        }

        $html .= '</ol>';
        $html .= '</nav>';

        return $html;
    }

    private function renderItem(BreadcrumbItem $item, bool $isActive): string
    {
        $activeClass = $isActive ? ' active' : '';
        $ariaCurrent = $isActive ? ' aria-current="page"' : '';

        $html = sprintf('<li class="breadcrumb-item%s"%s>', $activeClass, $ariaCurrent);

        // Détection si on a besoin du wrapper flex (si icône présente)
        $hasIcon = !empty($item->getIcon());
        $linkClass = $hasIcon ? ' class="d-flex align-items-center"' : '';

        // Construction du contenu (Icon + Label)
        $content = '';
        if ($hasIcon) {
            $content .= sprintf('<i class="ds %s me-1"></i>', $item->getIcon());
        }

        // Gestion du saut de ligne pour la lisibilité HTML si icône présente
        if ($hasIcon) {
            $content .= "\n" . $item->getName() . "\n";
        } else {
            $content .= $item->getName();
        }

        // Rendu Lien (si pas actif et lien existe) ou Span (si actif ou pas de lien)
        if ($isActive || $item->getLink() === null) {
            // Si actif avec icône, on met un span pour garder le style flex
            if ($hasIcon) {
                $html .= sprintf('<span%s>%s</span>', $linkClass, $content);
            } else {
                $html .= $content;
            }
        } else {
            $html .= sprintf('<a href="%s"%s>%s</a>', $item->getLink(), $linkClass, $content);
        }

        $html .= '</li>';

        return $html;
    }

    private function isAdmin(): bool
    {
        // Utilisation du Trait supposé
        return str_starts_with($this->getRequestPath(), self::ADMIN_BASE_ROUTE);
    }

    private function isDashboard(): bool
    {
        // Utilisation du Trait supposé
        return str_starts_with($this->getRequestPath(), self::DASHBOARD_BASE_ROUTE);
    }
}