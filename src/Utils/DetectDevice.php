<?php

/**
 * Service de détection d'appareils
 * 
 * Ce service utilise la librairie MobileDetect pour détecter le type d'appareil
 * et le navigateur utilisé par l'utilisateur.
 * 
 * Inspiré du template GitHub avec les mêmes fonctionnalités.
 * 
 * @author GitHub Copilot
 * @since 1.0.0
 * @version 1.0.0
 * @package Fagathe\CorePhp\Utils
 */

declare(strict_types=1);

namespace Fagathe\CorePhp\Utils;

use Detection\MobileDetect;
use Symfony\Component\HttpFoundation\Request;

/**
 * Service de détection d'appareils
 */
class DetectDevice
{
    private MobileDetect $detect;
    private ?Request $request = null;

    public function __construct(
    ) {
        $this->detect = new MobileDetect();
        $this->request = Request::createFromGlobals();
        $this->initializeDetector();
    }

    /**
     * Factory method pour créer une instance depuis PROJECT_DIR
     * 
     * @return self Instance du service
     */
    public static function createFromProjectDir(): self
    {
        if (!defined('PROJECT_DIR')) {
            throw new \RuntimeException('La constante PROJECT_DIR n\'est pas définie');
        }
        return new self();
    }

    /**
     * Initialise le détecteur avec les headers de la requête
     */
    private function initializeDetector(): void
    {
        if ($this->request && $this->request->headers->has('User-Agent')) {
            $userAgent = $this->request->headers->get('User-Agent', '');
            $this->detect->setUserAgent($userAgent);
        }
    }

    /**
     * Détermine le type d'appareil utilisé
     * 
     * @return DeviceEnum Type d'appareil détecté
     * 
     * @example
     * ```php
     * $detectDevice = new DetectDevice();
     * $deviceType = $detectDevice->getDeviceType();
     * echo $deviceType->value; // "Mobile", "Tablet", "Desktop", ou "Unknown Device"
     * ```
     */
    public function getDeviceType(): DeviceEnum
    {
        if (!$this->request) {
            return DeviceEnum::Unknown;
        }

        return match (true) {
            $this->detect->isTablet() => DeviceEnum::Tablet,
            $this->detect->isMobile() => DeviceEnum::Mobile,
            $this->detect->isUserAgentEmpty() => DeviceEnum::Unknown,
            default => DeviceEnum::Desktop,
        };
    }

    /**
     * Détermine le navigateur utilisé
     * 
     * @return BrowserEnum Navigateur détecté
     * 
     * @example
     * ```php
     * $detectDevice = new DetectDevice();
     * $browser = $detectDevice->getBrowser();
     * echo $browser->value; // "Chrome", "Firefox", etc.
     * ```
     */
    public function getBrowser(): BrowserEnum
    {
        if (!$this->request) {
            return BrowserEnum::Unknown;
        }

        $userAgent = $this->request->headers->get('User-Agent', '');

        return match (true) {
            $this->detect->isEdge() || str_contains($userAgent, 'Edg') => BrowserEnum::Edge,
            $this->detect->isSafari() && !$this->detect->isChrome() => BrowserEnum::Safari,
            $this->detect->isFirefox() => BrowserEnum::Firefox,
            $this->detect->isChrome() => BrowserEnum::Chrome,
            $this->detect->isOpera() => BrowserEnum::Opera,
            $this->detect->isUserAgentEmpty() => BrowserEnum::Unknown,
            default => BrowserEnum::fromString($this->extractBrowserFromUserAgent($userAgent)),
        };
    }

    /**
     * Obtient les informations complètes sur l'appareil
     * 
     * @return array<string, mixed> Informations sur l'appareil
     */
    public function getDeviceInfo(): array
    {
        $device = $this->getDeviceType();
        $browser = $this->getBrowser();

        return [
            'device' => $device->value,
            'device_description' => $device->getDescription(),
            'device_icon' => $device->getIcon(),
            'device_color' => $device->getColorClass(),
            'browser' => $browser->value,
            'browser_description' => $browser->getDescription(),
            'browser_icon' => $browser->getIcon(),
            'browser_color' => $browser->getColorClass(),
            'is_mobile' => $device->isMobile(),
            'is_chromium' => $browser->isChromiumBased(),
            'user_agent' => $this->request?->headers->get('User-Agent', 'Unknown'),
            'detected_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Vérifie si l'appareil est mobile (Mobile ou Tablet)
     * 
     * @return bool True si mobile ou tablette
     */
    public function isMobileDevice(): bool
    {
        return $this->getDeviceType()->isMobile();
    }

    /**
     * Vérifie si l'appareil est un ordinateur de bureau
     * 
     * @return bool True si ordinateur de bureau
     */
    public function isDesktop(): bool
    {
        return $this->getDeviceType() === DeviceEnum::Desktop;
    }

    /**
     * Obtient la version du navigateur si possible
     * 
     * @return string|null Version du navigateur ou null si indisponible
     */
    public function getBrowserVersion(): ?string
    {
        if (!$this->request) {
            return null;
        }

        $userAgent = $this->request->headers->get('User-Agent', '');
        $browser = $this->getBrowser();

        return match ($browser) {
            BrowserEnum::Chrome => $this->extractVersion($userAgent, '/Chrome\/([0-9.]+)/'),
            BrowserEnum::Firefox => $this->extractVersion($userAgent, '/Firefox\/([0-9.]+)/'),
            BrowserEnum::Safari => $this->extractVersion($userAgent, '/Version\/([0-9.]+).*Safari/'),
            BrowserEnum::Edge => $this->extractVersion($userAgent, '/Edg\/([0-9.]+)/'),
            BrowserEnum::Opera => $this->extractVersion($userAgent, '/OPR\/([0-9.]+)/'),
            default => null,
        };
    }

    /**
     * Obtient des statistiques sur les appareils détectés (pour analyse)
     * 
     * @return array<string, mixed> Statistiques
     */
    public function getDetectionStats(): array
    {
        $device = $this->getDeviceType();
        $browser = $this->getBrowser();

        return [
            'device_stats' => [
                'type' => $device->value,
                'is_mobile' => $device->isMobile(),
                'market_category' => $device === DeviceEnum::Desktop ? 'desktop' : 'mobile'
            ],
            'browser_stats' => [
                'name' => $browser->value,
                'is_chromium' => $browser->isChromiumBased(),
                'is_modern' => $browser->isModern(),
                'market_share' => $browser->getMarketShare()
            ],
            'detection_quality' => [
                'has_user_agent' => !empty($this->request?->headers->get('User-Agent')),
                'detector_version' => $this->detect->getVersion(),
                'confidence' => $device !== DeviceEnum::Unknown && $browser !== BrowserEnum::Unknown ? 'high' : 'low'
            ]
        ];
    }

    /**
     * Extrait le nom du navigateur depuis le User-Agent
     * 
     * @param string $userAgent User-Agent à analyser
     * @return string Nom du navigateur extrait
     */
    private function extractBrowserFromUserAgent(string $userAgent): string
    {
        $patterns = [
            'Chrome' => '/Chrome/i',
            'Firefox' => '/Firefox/i',
            'Safari' => '/Safari/i',
            'Edge' => '/Edge|Edg/i',
            'Opera' => '/Opera|OPR/i',
        ];

        foreach ($patterns as $browser => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return $browser;
            }
        }

        return 'Unknown Browser';
    }

    /**
     * Extrait la version depuis le User-Agent avec un pattern donné
     * 
     * @param string $userAgent User-Agent à analyser
     * @param string $pattern Pattern regex pour extraire la version
     * @return string|null Version extraite ou null
     */
    private function extractVersion(string $userAgent, string $pattern): ?string
    {
        if (preg_match($pattern, $userAgent, $matches)) {
            return $matches[1] ?? null;
        }

        return null;
    }

    /**
     * Met à jour le User-Agent pour la détection
     * 
     * @param string $userAgent Nouveau User-Agent à utiliser
     * @return self Instance courante pour chaînage
     */
    public function setUserAgent(string $userAgent): self
    {
        $this->detect->setUserAgent($userAgent);
        return $this;
    }

    /**
     * Obtient le User-Agent actuel
     * 
     * @return string User-Agent ou chaîne vide
     */
    public function getUserAgent(): string
    {
        return $this->request?->headers->get('User-Agent', '') ?? '';
    }
}