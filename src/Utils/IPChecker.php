<?php

/**
 * Service de vérification et récupération d'adresses IP
 * Version optimisée : Log uniquement lors des appels API externes.
 * Compatible PHP 8.0+ (suppression de curl_close).
 */

declare(strict_types=1);

namespace Fagathe\CorePhp\Utils;

use Fagathe\CorePhp\Logger\Logger;
use Fagathe\CorePhp\Enum\LoggerLevelEnum;
use Fagathe\CorePhp\Http\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\SecurityBundle\Security;

class IPChecker
{
    public const IP_COOKIE_NAME = '__ffr_v4';
    public const ENCODING_PREFIX = 'FFR___.';
    public const CACHE_DURATION_MINUTES = 15;

    private const LOG_FILE = 'ip-checker/find-ip';

    private const IP_SERVICES = [
        'http://httpbin.org/ip',
        'https://api.ipify.org?format=json',
        'https://api.ip.sb/ip',
        'https://ipapi.co/ip/'
    ];

    private Request $request;
    private Logger $logger;
    private ?Security $security = null;

    public function __construct(?Security $security = null)
    {
        $this->request = Request::createFromGlobals();
        $this->security = $security;
        $this->logger = new Logger(self::LOG_FILE, $security, false);
    }

    public static function createFromProjectDir(): self
    {
        if (!defined('PROJECT_DIR')) {
            throw new \RuntimeException('La constante PROJECT_DIR n\'est pas définie');
        }
        return new self();
    }

    public function getIp(): string
    {
        // 1. Tentative via Cache Cookie (AUCUN LOG)
        if (Cookie::has(self::IP_COOKIE_NAME)) {
            $retrievedIp = Cookie::get(self::IP_COOKIE_NAME);

            if ($retrievedIp && str_starts_with($retrievedIp, self::ENCODING_PREFIX)) {
                $decodedIp = base64_decode(str_replace(self::ENCODING_PREFIX, '', $retrievedIp));

                if ($this->isValidIpAddress($decodedIp)) {
                    // Succès cache : on retourne directement sans logger
                    return $decodedIp;
                }
            }
        }

        // 2. Appel API (Les logs se font à l'intérieur de getUserIPv4)
        $ipAddress = $this->getUserIPv4();

        // Stockage cookie (AUCUN LOG)
        if ($ipAddress !== 'unknown IP address') {
            $this->storeCookieNative($ipAddress);
        } else {
            // Seul log conservé ici : échec critique après toutes les tentatives API
            $this->generateLog(
                ['message' => 'Impossible de récupérer une IP valide après appels API'],
                ['action' => 'ip.get.failed'],
                LoggerLevelEnum::Error
            );
        }

        return $ipAddress;
    }

    /**
     * C'est ici que la logique d'appel API commence, donc on garde les logs.
     */
    private function getUserIPv4(): string
    {
        foreach (self::IP_SERVICES as $service) {
            try {
                // LOG : Début tentative API
                $this->generateLog(
                    ['message' => 'Tentative de récupération IP via API', 'service' => $service],
                    ['action' => 'ip.fetch.attempt'],
                    LoggerLevelEnum::Debug
                );

                $ip = $this->fetchIpFromService($service);

                if ($ip && $this->isValidIpAddress($ip)) {
                    // LOG : Succès API
                    $this->generateLog(
                        ['message' => 'IP récupérée avec succès via API', 'service' => $service],
                        ['action' => 'ip.fetch.success'],
                        LoggerLevelEnum::Info
                    );
                    return $ip;
                }
            } catch (\Exception $e) {
                // LOG : Erreur API
                $this->generateLog(
                    ['message' => 'Échec de récupération IP sur ce service', 'service' => $service, 'error' => $e->getMessage()],
                    ['action' => 'ip.fetch.error'],
                    LoggerLevelEnum::Warning
                );
                continue;
            }
        }

        // LOG : Echec total API
        $this->generateLog(
            ['message' => 'Tous les services IP ont échoué'],
            ['action' => 'ip.fetch.all_failed'],
            LoggerLevelEnum::Error
        );

        return 'unknown IP address';
    }

    /**
     * Détails techniques de l'appel cURL (Logs conservés)
     */
    private function fetchIpFromService(string $serviceUrl): ?string
    {
        $startTime = microtime(true);
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $serviceUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_USERAGENT => 'IPChecker/1.0',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $errorNo = curl_errno($curl);
        $error = curl_error($curl);

        // NOTE: curl_close($curl) a été supprimé car obsolète/redondant en PHP 8.0+
        // L'objet CurlHandle est automatiquement fermé quand il sort du scope.

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        if ($response === false || !empty($error) || $httpCode !== 200) {
            $isOffline = in_array($errorNo, [CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT]);

            $message = $isOffline ? 'Pas de connexion internet' : 'Erreur technique lors de l\'appel API (Code HTTP : ' . $httpCode . ')';

            // LOG : Erreur technique cURL
            $this->generateLog(
                [
                    'message' => $message,
                    'service' => $serviceUrl,
                    'error' => $error ?: 'Unknown error',
                    'curl_error_code' => $errorNo,
                    'http_code' => $httpCode,
                    'duration_ms' => $duration
                ],
                ['action' => 'ip.curl.error'],
                LoggerLevelEnum::Warning
            );
            throw new \RuntimeException("Erreur cURL: {$error}, HTTP: {$httpCode}");
        }

        // LOG : Succès technique cURL
        $this->generateLog(
            [
                'message' => 'Réponse cURL reçue',
                'service' => $serviceUrl,
                'http_code' => $httpCode,
                'duration_ms' => $duration
            ],
            ['action' => 'ip.curl.success'],
            LoggerLevelEnum::Debug
        );

        return $this->extractIpFromResponse($response, $serviceUrl);
    }

    private function extractIpFromResponse(string $response, string $serviceUrl): ?string
    {
        // Parsing JSON
        $decoded = json_decode($response, true);

        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['origin'])) {
            return trim($decoded['origin']);
        }

        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['ip'])) {
            return trim($decoded['ip']);
        }

        // Parsing Texte brut
        $ip = trim($response);
        $ip = preg_replace('/[^0-9.]/', '', $ip);

        if ($this->isValidIpAddress($ip)) {
            return $ip;
        }

        // LOG : Réponse API inutilisable (Seul log conservé pour le parsing)
        $this->generateLog(
            ['message' => 'Format de réponse API non reconnu', 'service' => $serviceUrl],
            ['action' => 'ip.extract.failed'],
            LoggerLevelEnum::Warning
        );

        return null;
    }

    private function isValidIpAddress(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private function storeCookieNative(string $ip): void
    {
        // Plus de logs ici
        $encodedIp = self::ENCODING_PREFIX . base64_encode($ip);

        Cookie::set(
            self::IP_COOKIE_NAME,
            $encodedIp,
            [
                'expires' => time() + (self::CACHE_DURATION_MINUTES * 60),
                'path' => '/',
                'domain' => null,
                'secure' => $this->request?->isSecure() ?? false,
                'httponly' => false,
                'samesite' => 'lax'
            ]
        );
    }

    /**
     * Méthodes utilitaires sans logs
     */

    public function getLocalIp(): string
    {
        if (!$this->request) {
            return 'unknown';
        }

        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            $ip = $this->request->server->get($header);
            if ($ip && $ip !== 'unknown') {
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $this->request->getClientIp() ?? 'unknown';
    }

    public function getIpInfo(): array
    {
        $publicIp = $this->getIp();
        $localIp = $this->getLocalIp();

        return [
            'public_ip' => $publicIp,
            'local_ip' => $localIp,
            'is_public_known' => $publicIp !== 'unknown IP address',
            'is_local_known' => $localIp !== 'unknown',
            'is_behind_proxy' => $publicIp !== $localIp,
            'user_agent' => $this->request?->headers->get('User-Agent', 'Unknown'),
            'retrieved_at' => date('Y-m-d H:i:s'),
            'cache_available' => isset($_COOKIE[self::IP_COOKIE_NAME]),
            'cache_duration_minutes' => self::CACHE_DURATION_MINUTES
        ];
    }

    public function refreshIp(): string
    {
        if (isset($_COOKIE[self::IP_COOKIE_NAME])) {
            setcookie(self::IP_COOKIE_NAME, '', time() - 3600, '/');
            unset($_COOKIE[self::IP_COOKIE_NAME]);
        }
        return $this->getUserIPv4();
    }

    public function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE) === false;
    }

    public function getServiceStats(): array
    {
        return [
            'available_services' => count(self::IP_SERVICES),
            'services' => self::IP_SERVICES,
            'cookie_name' => self::IP_COOKIE_NAME,
            'cache_duration' => self::CACHE_DURATION_MINUTES . ' minutes',
        ];
    }

    private function generateLog(array $content, array $context = [], LoggerLevelEnum $level = LoggerLevelEnum::Error): void
    {
        $this->logger->log($level, $content, $context);
    }
}