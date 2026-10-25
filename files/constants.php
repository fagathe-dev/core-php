<?php
declare(strict_types=1);

/*
 * Constantes legacy de core-php.
 *
 * Migration en cours vers des paramètres Symfony par thématique (config/*.yaml),
 * chargés par Fagathe\CorePhp\CorePhpBundle. Les define() ci-dessous restent la
 * source utilisée par le code existant : ne pas les supprimer tant que toutes les
 * briques ne lisent pas les paramètres.
 */

// Définir la constante du répertoire racine du projet
// (installé dans vendor/fagathe-dev/core-php : projet hôte ; sinon dépôt seul, ex. tests)
// @deprecated utiliser le paramètre %kernel.project_dir%
defined('PROJECT_DIR') || define('PROJECT_DIR', 'vendor' === basename(dirname(__DIR__, 3)) ? dirname(__DIR__, 4) : dirname(__DIR__));

if (is_file(PROJECT_DIR . '/vendor/autoload.php')) {
    require_once PROJECT_DIR . '/vendor/autoload.php';
}

use Fagathe\CorePhp\File\MimeType;

# Configuration constants

// Autres constantes utiles
// @deprecated utiliser le paramètre %core_php.uploader.public_dir% (config/my_uploader.yaml)
define('PUBLIC_DIR', 'public');

# 1. Logs configuration constants
// @deprecated utiliser le paramètre %core_php.logger.logs_dir% (config/my_logger.yaml)
define('LOGS_DIR', PROJECT_DIR . '/logs/');
// @deprecated utiliser le paramètre %core_php.logger.retention_delay% (config/my_logger.yaml)
define('LOGS_RETENTION_DELAY', 30); // days

# 2. Uploader configuration constants
// Chemin d'accès au répertoire d'upload (dans le dossier public)
// @deprecated utiliser le paramètre %core_php.uploader.upload_dir% (config/my_uploader.yaml)
define('UPLOAD_DIR', 'uploads');

// Taille maximale des fichiers autorisée (en octets)
// Exemple : 50 Mo
// @deprecated utiliser le paramètre %core_php.uploader.max_filesize% (config/my_uploader.yaml)
define('UPLOAD_MAX_FILESIZE', 50 * 1024 * 1024);

// Types MIME supportés par défaut (ces constantes seront utilisées par le Service)
// Pas d'équivalent YAML : valeur calculée, utiliser MimeType::getSupportedMimetypes()
define('UPLOAD_SUPPORTED_MIMES', MimeType::getSupportedMimetypes());

// Définit si les fichiers doivent être renommés avec un nom unique (UUID)
// @deprecated utiliser le paramètre %core_php.uploader.rename_file% (config/my_uploader.yaml)
define('UPLOAD_RENAME_FILE', true);

# 3. Email configuration constants
// @deprecated utiliser le paramètre %core_php.email.default_sender% (config/my_email.yaml)
define('DEFAULT_EMAIL_SENDER', 'contact@frederickagathe.fr');
// @deprecated utiliser le paramètre %core_php.email.logo_path% (config/my_email.yaml)
define('LOGO_PATH', '/images/logo.webp');
