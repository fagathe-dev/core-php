<?php
declare(strict_types=1);

// Définir la constante du répertoire racine du projet
define('PROJECT_DIR', dirname(__DIR__, 4));

require_once PROJECT_DIR . '/vendor/autoload.php';

use Fagathe\CorePhp\File\MimeType;

# Configuration constants

// Autres constantes utiles
define('PUBLIC_DIR', 'public');

# 1. Logs configuration constants
define('LOGS_DIR', PROJECT_DIR . '/logs/');
define('LOGS_RETENTION_DELAY', 30); // days

# 2. Uploader configuration constants
// Chemin d'accès au répertoire d'upload (dans le dossier public)
define('UPLOAD_DIR', 'uploads');

// Taille maximale des fichiers autorisée (en octets)
// Exemple : 50 Mo
define('UPLOAD_MAX_FILESIZE', 50 * 1024 * 1024);

// Types MIME supportés par défaut (ces constantes seront utilisées par le Service)
define('UPLOAD_SUPPORTED_MIMES', MimeType::getSupportedMimetypes());

// Définit si les fichiers doivent être renommés avec un nom unique (UUID)
define('UPLOAD_RENAME_FILE', true);

# 3. Email configuration constants
define('DEFAULT_EMAIL_SENDER', 'contact@frederickagathe.fr');
define('LOGO_PATH', '/images/logo.webp');