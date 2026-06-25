# Utils

Utilitaires de détection d'appareil/navigateur et de récupération d'adresse IP publique.

## Classes

| Classe | Rôle |
|---|---|
| `DetectDevice` | Détecte le type d'appareil et le navigateur via `MobileDetect` |
| `IPChecker` | Récupère l'IP publique de l'utilisateur avec cache cookie |

---

## DetectDevice

Utilise la librairie `mobiledetect/mobiledetectlib` pour identifier le device et le navigateur.

### Instanciation

```php
use Fagathe\CorePhp\Utils\DetectDevice;

$detector = new DetectDevice();
// Lit automatiquement le User-Agent depuis la requête HTTP courante
```

### Détecter le type d'appareil

```php
use Fagathe\CorePhp\Enum\DeviceEnum;

$device = $detector->getDeviceType(); // DeviceEnum

echo $device->value;              // "Desktop", "Mobile", "Tablet", "Terminal / CLI", "Unknown Device"
echo $device->getDescription();   // "Ordinateur de bureau", "Téléphone mobile"…
echo $device->getIcon();          // "ri-desktop-line", "ri-mobile-line"…
echo $device->getColorClass();    // "text-primary", "text-success"…
```

### Détecter le navigateur

```php
use Fagathe\CorePhp\Enum\BrowserEnum;

$browser = $detector->getBrowser(); // BrowserEnum

echo $browser->value;              // "Chrome", "Firefox", "Safari", "Edge", "Opera", "Unknown Browser"
echo $browser->getDescription();   // "Google Chrome", "Mozilla Firefox"…
echo $browser->getIcon();          // "ri-chrome-line", "ri-firefox-line"…
echo $browser->getColorClass();    // "text-warning", "text-danger"…
```

### Informations complètes

```php
$info = $detector->getDeviceInfo();
// [
//   'device'              => 'Desktop',
//   'device_description'  => 'Ordinateur de bureau',
//   'device_icon'         => 'ri-desktop-line',
//   'device_color'        => 'text-primary',
//   'browser'             => 'Chrome',
//   'browser_description' => 'Google Chrome',
//   'browser_icon'        => 'ri-chrome-line',
//   'browser_color'       => 'text-warning',
//   'is_mobile'           => false,
//   'is_chromium'         => true,
//   'user_agent'          => 'Mozilla/5.0 ...',
//   'detected_at'         => '2026-06-22 14:30:00'
// ]
```

### Helpers booléens

```php
$detector->isMobile();   // true si Mobile ou Tablet
```

---

## IPChecker

Récupère l'adresse IPv4 publique de l'utilisateur. Utilise un cache cookie pour éviter les appels API répétés.

### Prérequis

```php
define('LOGS_DIR', __DIR__ . '/var/logs');        // Pour les logs internes
define('LOGS_RETENTION_DELAY', 30);               // Rétention des logs
```

### Usage

```php
use Fagathe\CorePhp\Utils\IPChecker;

$checker = new IPChecker();
$ip = $checker->getIp(); // "92.184.100.42" ou "unknown IP address"
```

### Avec Symfony Security (pour les logs)

```php
$checker = new IPChecker($security);
$ip = $checker->getIp();
```

### Stratégie de récupération de l'IP

1. **Cache cookie** (`__ffr_v4`) : Si le cookie existe et contient une IP valide encodée, retourne immédiatement sans appel API.
2. **Services API externes** : Interroge en séquence plusieurs services jusqu'à obtenir une IP valide :
   - `http://httpbin.org/ip`
   - `https://api.ipify.org?format=json`
   - `https://api.ip.sb/ip`
   - `https://ipapi.co/ip/`
3. **Stockage en cookie** : L'IP récupérée est encodée et mise en cache cookie pour `CACHE_DURATION_MINUTES` (15 min).

### Constantes

| Constante | Valeur | Description |
|---|---|---|
| `IP_COOKIE_NAME` | `__ffr_v4` | Nom du cookie de cache |
| `CACHE_DURATION_MINUTES` | `15` | Durée du cache en minutes |
| `ENCODING_PREFIX` | `FFR___.` | Préfixe d'encodage de l'IP dans le cookie |

---

## Intégration dans le Logger

`DetectDevice` et `IPChecker` sont utilisés automatiquement par `Logger` pour enrichir chaque entrée de log avec le contexte de l'utilisateur. Voir [docs/logger.md](logger.md).
