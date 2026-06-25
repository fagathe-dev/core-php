# Logger

Système de journalisation JSON avec rotation automatique des fichiers, enrichissement contextuel (IP, device, browser, utilisateur) et rendu HTML via template.

## Architecture

```
Logger (façade principale)
  └── JsonLogService (lecture/écriture JSON)
        └── JsonFileHandler (I/O fichiers)
        └── JsonLoggerRotation (nettoyage des vieux fichiers)
  └── Log (DTO d'une entrée de log)
  └── LoggerTemplate (rendu HTML d'un log)
```

## Prérequis

Les constantes suivantes doivent être définies avant d'utiliser le Logger :

```php
define('LOGS_DIR', __DIR__ . '/var/logs');
define('LOGS_RETENTION_DELAY', 30); // Nombre de jours de rétention
```

---

## Logger — Usage principal

```php
use Fagathe\CorePhp\Logger\Logger;

$logger = new Logger('my-feature');
// Crée (ou alimente) le fichier : LOGS_DIR/my-feature-22-06-2026.json
```

### Méthodes de log par niveau

```php
$logger->info(['message' => 'Utilisateur connecté', 'user' => 'alice']);
$logger->debug(['message' => 'Données reçues', 'payload' => $data]);
$logger->notice(['message' => 'Opération inhabituelle']);
$logger->warning(['message' => 'Quota proche du maximum']);
$logger->error(['message' => 'Échec de la requête SQL', 'exception' => $e->getMessage()]);
$logger->critical(['message' => 'Service indisponible']);
```

### Avec contexte additionnel

```php
$logger->info(
    content: ['message' => 'Commande créée', 'order_id' => 42],
    context: ['action' => 'order.create', 'uid' => 'user_123']
);
```

### Clés autorisées

**Contenu** (`Log::CONTENT_KEYS`) : `data`, `exception`, `message`, `response`, `request`, `sql`

**Contexte** (`Log::CONTEXT_KEYS`) : `ip`, `device`, `browser`, `action`, `uid`, `user_agent`, `referer`, `url`, `method`, `origin`

> Les clés sensibles (`password`, `token`, `secret`, `api_key`…) sont automatiquement masquées dans le log.

---

## Enrichissement automatique

`Logger` enrichit chaque entrée automatiquement avec :
- L'adresse IP de l'utilisateur (via `IPChecker`, désactivable)
- Le type d'appareil (via `DetectDevice`)
- Le navigateur détecté
- L'URL, la méthode HTTP, le referer
- L'utilisateur Symfony (si `Security` est injecté)

```php
// Avec Symfony Security (pour logger l'utilisateur connecté)
$logger = new Logger('orders', $security, logIP: true);

// Sans log de l'IP
$logger = new Logger('orders', logIP: false);
```

---

## Rotation des logs

```php
use Fagathe\CorePhp\Logger\JsonLogService;
use Symfony\Bundle\SecurityBundle\Security;

$service = new JsonLogService('/path/to/log.json', $security);

// Supprime les fichiers plus vieux que LOGS_RETENTION_DELAY jours
$report = $service->deleteOldFiles();
// [
//   'success' => true,
//   'files_deleted' => 5,
//   ...
// ]
```

---

## JsonLogService — Accès bas niveau

```php
use Fagathe\CorePhp\Logger\JsonLogService;
use Fagathe\CorePhp\Logger\Log;
use Fagathe\CorePhp\Enum\LoggerLevelEnum;

$service = new JsonLogService('/path/to/log-22-06-2026.json');

// Lire tous les logs
$logs = $service->readLogs(); // array

// Sauvegarder manuellement un log
$log = (new Log())
    ->setLevel(LoggerLevelEnum::Info)
    ->setContent('message', 'Test')
    ->setContext('action', 'manual.test');

$service->save($log); // bool
```

---

## LoggerTrait — Dans les services

Le `LoggerTrait` (voir [docs/traits.md](traits.md)) permet à n'importe quel service de logger avec un nom de fichier généré automatiquement depuis le nom de la classe.

```php
use Fagathe\CorePhp\Trait\LoggerTrait;

class OrderService
{
    use LoggerTrait;

    public function create(array $data): void
    {
        // Fichier de log : LOGS_DIR/service/order-service-22-06-2026.json
        $this->generateLog(
            LoggerLevelEnum::Info,
            ['message' => 'Commande créée', 'data' => $data],
            ['action' => 'order.create']
        );
    }
}
```

---

## Rendu HTML avec LoggerTemplate

`LoggerTemplate` génère un affichage Bootstrap 5 (Accordion) pour afficher un log en vue.

```php
use Fagathe\CorePhp\Logger\LoggerTemplate;

$html = (new LoggerTemplate($log))->generate();
```

Ou via l'extension Twig `generate_log()` (voir [docs/twig.md](twig.md)) :

```twig
{{ generate_log(log) }}
```

---

## Format d'un fichier de log

```json
[
  {
    "id": "uuid-...",
    "level": "info",
    "timestamp": "2026-06-22T14:30:00+02:00",
    "content": {
      "message": "Utilisateur connecté"
    },
    "context": {
      "ip": "192.168.1.1",
      "device": "Desktop",
      "browser": "Chrome",
      "action": "user.login",
      "url": "/login"
    }
  }
]
```
