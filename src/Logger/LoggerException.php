<?php

namespace Fagathe\CorePhp\Logger;

use Exception;

/**
 * Exception spécifique au système de logging.
 * 
 * Utilisée pour les erreurs liées à la gestion des logs
 * (persistance, lecture, format, etc.).
 * 
 * @author Journal App
 */
class LoggerException extends Exception
{
    /**
     * Constructeur de l'exception de logging.
     * 
     * @param string $message Message d'erreur
     * @param int $code Code d'erreur (optionnel)
     * @param Exception|null $previous Exception précédente (optionnel)
     */
    public function __construct(string $message, int $code = 0, ?Exception $previous = null)
    {
        parent::__construct("[LOGGING ERROR] " . $message, $code, $previous);
    }
}