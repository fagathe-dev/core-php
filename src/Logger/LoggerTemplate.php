<?php

namespace Fagathe\CorePhp\Logger;

use Fagathe\CorePhp\Enum\BrowserEnum;
use Fagathe\CorePhp\Enum\DeviceEnum;
use Fagathe\CorePhp\Logger\Log;

/**
 * Template de rendu HTML pour les logs.
 * Génère un affichage formaté et stylisé des logs
 * en utilisant le composant Accordion du Design System Velzon.
 */
final class LoggerTemplate
{
    private string $html = '';
    private string $htmlID = '';

    private const PHONE_ICON = 'smartphone';
    private const TABLET_ICON = 'tablet';
    private const DESKTOP_ICON = 'computer';
    private const CLI_ICON = 'terminal-box';
    private const NO_MARGIN_BOTTOM_CLASS = 'mb-0';

    public function __construct(private readonly Log $log)
    {
        $this->generateID();
    }

    private function getHTML(): string
    {
        return $this->html;
    }

    private function addHTML(string $html): void
    {
        $this->html .= $html;
    }

    private function getHtmlID(): string
    {
        return $this->htmlID;
    }

    private function setHtmlID(string $htmlID): self
    {
        $this->htmlID = $htmlID;
        return $this;
    }

    private function hasContent(): bool
    {
        return $this->log->hasContext('action') ||
            $this->log->hasContext('uid') ||
            (is_array($this->log->getContents()) && count($this->log->getContents()) > 0);
    }

    /**
     * Génère l'en-tête du log (Structure Accordion Item + Header Velzon).
     */
    private function generateHeader(): void
    {
        $logLevel = $this->log->getLevel();
        $color = $logLevel?->getColor() ?? 'secondary';
        $icon = $logLevel?->getIcon() ?? 'mobile';
        $timestamp = $this->log->getTimestamp()?->format('d/m/Y H:i:s') ?? 'N/A';
        $hasContent = $this->hasContent();

        // Identifiants uniques pour lier le bouton au contenu (Standard Bootstrap 5)
        $collapseId = $this->getHtmlID();
        $headingId = 'heading_' . $collapseId;

        // Base de l'accordéon bootstrap
        $buttonClasses = 'accordion-button collapsed no-icon';
        $buttonClasses .= 'mb-0 p-3 shadow-none';

        $attributes = '';

        if ($hasContent) {
            // Configuration pour Velzon / Bootstrap 5 natif
            $attributes = sprintf(
                ' data-bs-toggle="collapse" data-bs-target="#%s" aria-expanded="false" aria-controls="%s"',
                $collapseId,
                $collapseId
            );
        } else {
            // Si pas de contenu : pas de chevron, pas de clic
            $buttonClasses .= ' no-icon';
            $attributes = ' style="cursor: default;"';
        }

        // --- Construction du contenu texte du header ---
        $deviceIcon = $this->getDeviceIcon();
        $browserInfo = $this->getBrowserInfo();

        $headerText = '<span class="d-flex align-items-center gap-2 flex-wrap text-body">';
        $headerText .= '<span class="badge bg-' . $color . '-subtle text-' . $color . '">';
        $headerText .= '<i class="' . $icon . ' me-1"></i>';
        $headerText .= ucfirst($logLevel?->value ?? 'unknown') . '</span>';
        $headerText .= '<span class="opacity-50 mx-1">|</span>';
        $headerText .= '<span class="font-monospace">' . $timestamp . '</span>';

        if ($deviceIcon) {
            $headerText .= '<span class="opacity-50 mx-1">|</span>' . $deviceIcon;
        }

        if ($browserInfo) {
            $headerText .= $browserInfo;
        }

        $ipInfo = $this->getIpInfo();

        if ($ipInfo !== null) {
            $headerText .= '<span class="opacity-50 mx-1">|</span>' . $ipInfo;
        }
        $headerText .= '</span>';

        // --- Génération du HTML ---

        // 1. Wrapper de l'item avec la classe "shadow" de Velzon
        $this->addHTML('<div class="accordion-item shadow mb-2 overflow-hidden" style="border-color: transparent;">');

        // 2. Header (h2) avec l'ID pour le aria-labelledby
        $this->addHTML('<h2 class="accordion-header m-0" id="' . $headingId . '">');

        // 3. Bouton déclencheur
        $this->addHTML('<button class="' . $buttonClasses . '" type="button"' . $attributes . '>');
        $this->addHTML($headerText);
        $this->addHTML('</button>');

        $this->addHTML('</h2>');
    }

    /**
     * Génère le contenu détaillé du log (Accordion Body Velzon).
     */
    private function generateContent(): void
    {
        if (!$this->hasContent()) {
            return;
        }

        $collapseId = $this->getHtmlID();
        $headingId = 'heading_' . $collapseId;

        // --- Structure Collapse Velzon avec aria-labelledby ---
        $this->addHTML('<div id="' . $collapseId . '" class="accordion-collapse collapse" aria-labelledby="' . $headingId . '">');

        $this->addHTML('<div class="accordion-body bg-light border-top">');

        $titleClass = 'fw-bold text-primary ' . self::NO_MARGIN_BOTTOM_CLASS;

        // --- Le reste de tes générateurs de contenu (inchangé, c'est de la mise en page interne) ---
        if ($this->log->hasContext('uid')) {
            $this->addHTML('<div class="mb-3">');
            $this->addHTML('<p class="' . $titleClass . '">Utilisateur :</p>');
            $this->addHTML('<p class="text-muted mb-0">' . htmlspecialchars($this->log->getContext('uid')) . '</p>');
            $this->addHTML('</div>');
        }

        if ($this->log->hasContext('action')) {
            $this->addHTML('<div class="mb-3">');
            $this->addHTML('<p class="' . $titleClass . '">Action :</p>');
            $this->addHTML('<p class="text-muted mb-0"><code>' . htmlspecialchars($this->log->getContext('action')) . '</code></p>');
            $this->addHTML('</div>');
        }

        if ($this->log->hasContent('message')) {
            $this->addHTML('<div class="mb-3">');
            $this->addHTML('<p class="' . $titleClass . '">Message :</p>');
            $this->addHTML('<div class="p-3 bg-white border rounded">');
            $this->addHTML(nl2br(htmlspecialchars($this->log->getContent('message'))));
            $this->addHTML('</div>');
            $this->addHTML('</div>');
        }

        if ($this->log->hasContent('data')) {
            $this->addHTML('<div class="mb-3">');
            $this->addHTML('<p class="' . $titleClass . '">Données :</p>');
            $this->addHTML('<div class="border rounded bg-white">');
            $this->addHTML('<pre class="p-3 mb-0" style="max-height: 300px; overflow-y: auto;"><code class="language-json">' .
                $this->formatJson($this->log->getContent('data')) .
                '</code></pre>');
            $this->addHTML('</div>');
            $this->addHTML('</div>');
        }

        if ($this->log->hasContent('exception')) {
            $this->addHTML('<div class="mb-3">');
            $this->addHTML('<p class="' . $titleClass . '">Exception :</p>');
            $this->addHTML('<div class="alert alert-danger border rounded mb-0">');
            $this->addHTML('<pre class="mb-0 text-wrap"><code>' .
                htmlspecialchars($this->formatJson($this->log->getContent('exception'))) .
                '</code></pre>');
            $this->addHTML('</div>');
            $this->addHTML('</div>');
        }

        if ($this->log->hasContent('sql')) {
            $this->addHTML('<div class="mb-3">');
            $this->addHTML('<p class="' . $titleClass . '">SQL :</p>');
            $this->addHTML('<div class="alert alert-info border rounded mb-0">');
            $this->addHTML('<pre class="mb-0 text-wrap"><code class="language-sql">' .
                htmlspecialchars($this->log->getContent('sql')) .
                '</code></pre>');
            $this->addHTML('</div>');
            $this->addHTML('</div>');
        }

        $this->generateTechnicalInfo();

        $this->addHTML('</div>');
        $this->addHTML('</div>');
    }

    private function end(): void
    {
        $this->addHTML('</div>');
    }

    private function generateTechnicalInfo(): void
    {
        $hasDevice = $this->log->hasContext('device') || $this->log->hasContext('browser');
        $hasIp = $this->log->hasContext('ip') || $this->log->hasContext('local_ip');
        $hasUserAgent = $this->log->hasContext('user_agent');
        $hasUrl = $this->log->hasContext('url') || $this->log->hasContext('method');

        if (!$hasDevice && !$hasIp && !$hasUserAgent && !$hasUrl) {
            return;
        }

        $titleClass = 'fw-bold text-primary ' . self::NO_MARGIN_BOTTOM_CLASS;

        $this->addHTML('<div class="mb-3">');
        $this->addHTML('<p class="' . $titleClass . '">Informations techniques :</p>');
        $this->addHTML('<div class="p-3 bg-white border rounded">');

        if ($hasDevice)
            $this->generateDeviceInfo();
        if ($hasIp)
            $this->generateIpDetails();
        if ($hasUrl)
            $this->generateRequestInfo();
        if ($hasUserAgent)
            $this->generateUserAgentInfo();

        $this->addHTML('</div>');
        $this->addHTML('</div>');
    }

    private function generateDeviceInfo(): void
    {
        $device = $this->log->getContext('device');
        $deviceDescription = $this->log->getContext('device_description');
        $browser = $this->log->getContext('browser');
        $browserDescription = $this->log->getContext('browser_description');
        $browserVersion = $this->log->getContext('browser_version');
        $isChromium = $this->log->getContext('is_chromium');

        if ($device || $browser) {
            $this->addHTML('<div class="row mb-2">');
            $this->addHTML('<div class="col-sm-3 text-muted">Appareil</div>');
            $this->addHTML('<div class="col-sm-9">');

            if ($device) {
                $deviceIcon = '';
                try {
                    $deviceIcon = $this->getDeviceIcon();
                } catch (\Exception $e) {
                }

                $this->addHTML($deviceIcon . '&nbsp;&nbsp;' . htmlspecialchars($deviceDescription ?: $device));
            }

            if ($browser) {
                if ($device) {
                    $this->addHTML('&nbsp;-&nbsp;');
                }

                $this->addHTML(htmlspecialchars($browserDescription ?: $browser));

                if ($browserVersion) {
                    $this->addHTML(' <span class="text-muted small ms-1">v' . htmlspecialchars($browserVersion) . '</span>');
                }

                if ($isChromium === true || $isChromium === 'true') {
                    $this->addHTML(' <span class="badge bg-secondary ms-2">Chromium</span>');
                }
            }

            $this->addHTML('</div>');
            $this->addHTML('</div>');
        }
    }

    private function generateIpDetails(): void
    {
        $publicIp = $this->log->getContext('ip');
        $localIp = $this->log->getContext('local_ip');
        $isCached = $this->log->getContext('ip_cached');
        $isBehindProxy = $this->log->getContext('behind_proxy');

        if ($publicIp || $localIp) {
            $this->addHTML('<div class="row mb-2">');
            $this->addHTML('<div class="col-sm-3 text-muted">Réseau</div>');
            $this->addHTML('<div class="col-sm-9">');

            if ($publicIp && $publicIp !== 'unknown' && $publicIp !== 'unknown IP address') {
                $this->addHTML('<i class="ri-global-line text-info me-2"></i>');
                $this->addHTML('<span class="font-monospace">' . htmlspecialchars($publicIp) . '</span>');

                if ($isCached === true || $isCached === 'true') {
                    $this->addHTML(' <span class="badge bg-success ms-2">Cache</span>');
                }

                if ($isBehindProxy === true || $isBehindProxy === 'true') {
                    $this->addHTML(' <span class="badge bg-warning ms-2">Proxy</span>');
                }

                if ($localIp && $localIp !== $publicIp && $localIp !== 'unknown') {
                    $this->addHTML('<br><i class="ri-router-line text-muted me-2"></i>');
                    $this->addHTML('<span class="text-muted font-monospace small">Local: ' . htmlspecialchars($localIp) . '</span>');
                }
            } elseif ($localIp && $localIp !== 'unknown') {
                $this->addHTML('<i class="ri-router-line text-muted me-2"></i>');
                $this->addHTML('<span class="font-monospace">' . htmlspecialchars($localIp) . '</span>');
            }

            $this->addHTML('</div>');
            $this->addHTML('</div>');
        }
    }

    private function generateRequestInfo(): void
    {
        $url = $this->log->getContext('url');
        $method = $this->log->getContext('method');
        $referer = $this->log->getContext('referer');

        if ($url || $method) {
            $this->addHTML('<div class="row mb-2">');
            $this->addHTML('<div class="col-sm-3 text-muted">Requête</div>');
            $this->addHTML('<div class="col-sm-9">');

            if ($method) {
                $methodClass = match (strtoupper($method)) {
                    'GET' => 'bg-success',
                    'POST' => 'bg-primary',
                    'PUT' => 'bg-warning',
                    'DELETE' => 'bg-danger',
                    default => 'bg-secondary'
                };

                $this->addHTML('<span class="badge ' . $methodClass . ' me-2">' . htmlspecialchars($method) . '</span>');
            }

            if ($url) {
                $this->addHTML('<span class="font-monospace small text-break">' . htmlspecialchars($url) . '</span>');
            }

            if ($referer) {
                $this->addHTML('<br><i class="ri-external-link-line text-muted me-2"></i>');
                $this->addHTML('<small class="text-muted text-break">Ref: ' . htmlspecialchars($referer) . '</small>');
            }

            $this->addHTML('</div>');
            $this->addHTML('</div>');
        }
    }

    private function generateUserAgentInfo(): void
    {
        $userAgent = $this->log->getContext('user_agent');

        if ($userAgent && $userAgent !== 'Unknown') {
            $this->addHTML('<div class="row">');
            $this->addHTML('<div class="col-sm-3 text-muted">User Agent</div>');
            $this->addHTML('<div class="col-sm-9">');
            $this->addHTML('<small class="font-monospace text-muted d-block text-break">');
            $this->addHTML(htmlspecialchars($userAgent));
            $this->addHTML('</small>');
            $this->addHTML('</div>');
            $this->addHTML('</div>');
        }
    }

    private function getDeviceIcon(): ?string
    {
        $device = $this->log->getContext('device');
        if (!$device)
            return null;

        try {
            $deviceEnum = DeviceEnum::fromString($device);
            $icon = $deviceEnum->getIcon();

        } catch (\Exception $e) {
            $icon = match (strtolower($device)) {
                'mobile', 'phone' => self::PHONE_ICON,
                'tablet' => self::TABLET_ICON,
                'desktop', 'computer' => self::DESKTOP_ICON,
                'terminal', 'cli', 'console' => self::CLI_ICON,
                default => 'circle-question',
            };
            $icon = 'ri-' . $icon . '-line';
        }

        return '<i class="' . $icon . '"></i>';
    }

    private function getBrowserInfo(): ?string
    {
        $browser = $this->log->getContext('browser');

        if (!$browser || $browser === 'Unknown') {
            return null;
        }

        if (strtolower($browser) === 'console') {
            return '<span class="small">Terminal</span>';
        }

        try {
            $browserEnum = BrowserEnum::fromString($browser);
            $version = $this->log->getContext('browser_version');
            $versionText = $version ? " v{$version}" : '';

            return '<span class="d-flex align-items-center">' .
                '<i class="' . $browserEnum->getIcon() . ' ' . $browserEnum->getColorClass() . ' me-1" title="' . htmlspecialchars($browserEnum->getDescription()) . '"></i>' .
                '<span class="small">' . htmlspecialchars($browser . $versionText) . '</span>' .
                '</span>';
        } catch (\Exception $e) {
            return '<span class="small">' . htmlspecialchars($browser) . '</span>';
        }
    }

    private function getIpInfo(): ?string
    {
        $ip = $this->log->getContext('ip');

        if (!$ip || $ip === null || $ip === 'unknown' || $ip === 'unknown IP address') {
            return null;
        }

        return '<span class="d-flex align-items-center">' .
            '<i class="ri-global-line text-info me-1" title="IP Publique"></i>' .
            '<span class="font-monospace small">' . htmlspecialchars($ip) . '</span>' .
            '</span>';
    }

    private function formatJson(mixed $data): string
    {
        if (is_string($data))
            return $data;
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function generateID(): void
    {
        $level = strtoupper($this->log->getLevel()?->value ?? 'LOG');
        $timestamp = $this->log->getTimestamp()?->format('YmdHis') ?? uniqid();
        $this->setHtmlID($level . '_' . $timestamp . '_' . uniqid());
    }

    private function start(): void
    {
    }

    public function generate(): string
    {
        $this->start();
        $this->generateHeader();
        $this->generateContent();
        $this->end();

        return $this->getHTML();
    }
}