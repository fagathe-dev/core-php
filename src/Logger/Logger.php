<?php

namespace Fagathe\CorePhp\Logger;

use Fagathe\CorePhp\Enum\DeviceEnum;
use Fagathe\CorePhp\Enum\LoggerLevelEnum;
use Fagathe\CorePhp\Trait\DatetimeTrait;
use Fagathe\CorePhp\Utils\DetectDevice;
use Fagathe\CorePhp\Utils\IPChecker;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Logger customisé pour l'application Journal.
 * 
 * Fournit une interface simple pour enregistrer des logs
 * avec contexte automatique (utilisateur, IP, navigateur, etc.).
 * Étend JsonLogService pour la gestion des fichiers.
 * 
 * @author Journal App
 */
final class Logger extends JsonLogService
{
    use DatetimeTrait;

    private ?DetectDevice $detectDevice = null;
    private ?IPChecker $ipChecker = null;
    private Request $request;
    private string $logFile;

    public const LOG_CONTEXT = '__journal_log_context';
    public const LOG_UUID = '__journal_log_uuid';

    public function __construct(
        string $file,
        private readonly ?Security $security = null,
        private readonly bool $logIP = true
    ) {
        // Charger le chemin complet du fichier : LOGS_DIR . $file . DateTime::format('d-m-Y') . '.json'
        $this->logFile = $file;
        $dateStr = $this->formatDateTime('d-m-Y');
        $logFilePath = LOGS_DIR . '/' . $file . '-' . $dateStr . '.json';

        parent::__construct($logFilePath);

        // Gestion sécurisée de la création de Request pour éviter les FileNotFoundException
        // lors des uploads de fichiers temporaires
        try {
            $this->request = Request::createFromGlobals();
        } catch (\Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException $e) {
            // Si un fichier temporaire n'existe plus (après upload), on crée une request vide
            // pour éviter de casser le logging
            $this->request = new Request();
        }
    }

    /**
     * Détermine si on est en contexte CLI.
     * 
     * @return bool
     */
    private function isCli(): bool
    {
        return $this->request === null || PHP_SAPI === 'cli';
    }

    /**
     * Initialise les services nécessaires (lazy loading).
     * 
     * @return void
     */
    private function initializeServices(): void
    {
        if ($this->detectDevice === null) {
            $this->detectDevice = new DetectDevice();
        }

        if ($this->ipChecker === null) {
            $this->ipChecker = new IPChecker($this->security);
        }
    }

    /**
     * Log un message de niveau Info.
     * 
     * @param array $content Contenu du log
     * @param array $context Contexte additionnel
     * @return void
     */
    public function info(array $content = [], array $context = []): void
    {
        $this->log(LoggerLevelEnum::Info, $content, $context);
    }

    /**
     * Log un message de niveau Error.
     * 
     * @param array $content Contenu du log
     * @param array $context Contexte additionnel
     * @return void
     */
    public function error(array $content = [], array $context = []): void
    {
        $this->log(LoggerLevelEnum::Error, $content, $context);
    }

    /**
     * Log un message de niveau Warning.
     * 
     * @param array $content Contenu du log
     * @param array $context Contexte additionnel
     * @return void
     */
    public function warning(array $content = [], array $context = []): void
    {
        $this->log(LoggerLevelEnum::Warning, $content, $context);
    }

    /**
     * Log un message de niveau Debug.
     * 
     * @param array $content Contenu du log
     * @param array $context Contexte additionnel
     * @return void
     */
    public function debug(array $content = [], array $context = []): void
    {
        $this->log(LoggerLevelEnum::Debug, $content, $context);
    }

    /**
     * Log un message de niveau Critical.
     * 
     * @param array $content Contenu du log
     * @param array $context Contexte additionnel
     * @return void
     */
    public function critical(array $content = [], array $context = []): void
    {
        $this->log(LoggerLevelEnum::Critical, $content, $context);
    }

    /**
     * Log un message de niveau Notice.
     * 
     * @param array $content Contenu du log
     * @param array $context Contexte additionnel
     * @return void
     */
    public function notice(array $content = [], array $context = []): void
    {
        $this->log(LoggerLevelEnum::Notice, $content, $context);
    }

    /**
     * Log un message avec un niveau spécifié.
     * 
     * @param LoggerLevelEnum $level Niveau de log
     * @param array $content Contenu principal du log
     * @param array $context Contexte additionnel
     * @return void
     */
    public function log(LoggerLevelEnum $level = LoggerLevelEnum::Info, array $content = [], array $context = []): void
    {
        try {
            $now = $this->now();
            $dateTime = $now->format('Y-m-d H:i:s');

            // Crée le log
            $log = (new Log())
                ->setLevel($level)
                ->setContent($content)
                ->setContext($this->getContext())
                ->setTimestamp($dateTime)
                ->setId('LOG_' . $now->format('YmdHis') . '_' . uniqid());

            // Ajoute le contexte personnalisé (y compris url/method/origin pour appels cURL)
            foreach ($context as $key => $value) {
                if (in_array($key, Log::CONTEXT_KEYS)) {
                    $log->addContext($key, (string) $value);
                } elseif (in_array($key, Log::CONTENT_KEYS)) {
                    $log->addContent($key, $value);
                }
            }

            // Ajouter origin dans context (peut être null pour appels externes)
            if (!isset($context['origin'])) {
                $log->addContext('origin', $this->getOrigin());
            }

            // Sauvegarde le log
            $this->save($log);

        } catch (\Throwable $e) {
            // En cas d'erreur du logger lui-même, on utilise error_log de PHP
            error_log('Logger Error: ' . $e->getMessage());
        }
    }



    /**
     * Obtient le contexte automatique de la requête.
     * 
     * @return array
     */
    private function getContext(): array
    {
        $context = [];

        // Contexte CLI
        if ($this->isCli()) {
            $context['device'] = DeviceEnum::Terminal->value;
            $context['browser'] = 'Console';
            $context['method'] = 'CLI';
        } else {
            // Lazy loading : n'initialise les services lourds que si nécessaire
            $this->initializeServices();

            // IP de l'utilisateur avec le service IPChecker (IP publique uniquement)
            if ($this->logIP) {
                $ipInfo = $this->ipChecker->getIpInfo();
                $context['ip'] = $ipInfo['public_ip'];
            }

            // User Agent pour détection du device/browser uniquement
            $userAgent = $this->request->headers->get('User-Agent');
            if ($userAgent) {
                // Détection avancée avec les nouveaux services
                $deviceInfo = $this->detectDevice->getDeviceInfo();
                $context['device'] = $deviceInfo['device'];
                $context['browser'] = $deviceInfo['browser'];
            }

            // URL et méthode de la requête HTTP
            $context['url'] = $this->request->getUri();
            $context['method'] = $this->request->getMethod();
        }

        // Utilisateur connecté (sinon null sera omis par défaut)
        $user = $this->security?->getUser();
        if ($user) {
            $context['uid'] = $user->getUserIdentifier();
        }

        return $context;
    }

    /**
     * Obtient l'origine du log (nom du fichier de log).
     * 
     * @return string
     */
    private function getOrigin(): string
    {
        return $this->logFile;
    }
}