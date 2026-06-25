# Enum

Énumérations PHP 8.1 utilisées dans le projet. Elles partagent un pattern commun avec méthodes utilitaires (`getIcon()`, `getColor()`, etc.).

## BrowserEnum

Détecte et représente le navigateur de l'utilisateur.

```php
use Fagathe\CorePhp\Enum\BrowserEnum;

$browser = BrowserEnum::Chrome;

echo $browser->value;              // "Chrome"
echo $browser->getDescription();   // "Google Chrome"
echo $browser->getIcon();          // "ri-chrome-line"
echo $browser->getColorClass();    // "text-warning"
```

### Valeurs disponibles

| Case | Valeur | Description |
|---|---|---|
| `Chrome` | `Chrome` | Google Chrome |
| `Firefox` | `Firefox` | Mozilla Firefox |
| `Safari` | `Safari` | Apple Safari |
| `Edge` | `Edge` | Microsoft Edge |
| `Opera` | `Opera` | Opera Browser |
| `Unknown` | `Unknown Browser` | Navigateur inconnu |

---

## DeviceEnum

Représente le type d'appareil détecté.

```php
use Fagathe\CorePhp\Enum\DeviceEnum;

$device = DeviceEnum::Mobile;

echo $device->value;             // "Mobile"
echo $device->getDescription();  // "Téléphone mobile"
echo $device->getIcon();         // "ri-mobile-line"
echo $device->getColorClass();   // "text-success"
```

### Valeurs disponibles

| Case | Valeur | Description |
|---|---|---|
| `Desktop` | `Desktop` | Ordinateur de bureau |
| `Mobile` | `Mobile` | Téléphone mobile |
| `Tablet` | `Tablet` | Tablette |
| `Terminal` | `Terminal / CLI` | Terminal / CLI |
| `Unknown` | `Unknown Device` | Appareil inconnu |

---

## HumanDueDateEnum

Représente une échéance sous forme lisible (utilisé pour les tâches, tickets, etc.).

```php
use Fagathe\CorePhp\Enum\HumanDueDateEnum;

// Récupérer le label d'une valeur
echo HumanDueDateEnum::getMap(HumanDueDateEnum::Today);   // "Aujourd'hui"
echo HumanDueDateEnum::getMap('tomorrow');                 // "Demain"

// Récupérer la map complète [valeur => label]
$map = HumanDueDateEnum::getMap();
// ['overdue' => 'En retard', 'today' => "Aujourd'hui", ...]

// Récupérer uniquement les valeurs
$values = HumanDueDateEnum::values();
// ['overdue', 'today', 'tomorrow', 'this_week', 'next_week', 'later']

// Choix pour un formulaire [valeur => label]
$choices = HumanDueDateEnum::choices();
```

### Valeurs disponibles

| Case | Valeur | Label |
|---|---|---|
| `Overdue` | `overdue` | En retard |
| `Today` | `today` | Aujourd'hui |
| `Tomorrow` | `tomorrow` | Demain |
| `ThisWeek` | `this_week` | Cette semaine |
| `NextWeek` | `next_week` | La semaine prochaine |
| `Later` | `later` | Plus tard |

---

## LoggerLevelEnum

Niveaux de gravité pour le système de logs, du moins critique au plus critique.

```php
use Fagathe\CorePhp\Enum\LoggerLevelEnum;

$level = LoggerLevelEnum::Warning;

echo $level->value;          // "warning"
echo $level->getColor();     // "warning"  (classe Bootstrap)
echo $level->getIcon();      // "ri-alert-line"
echo $level->getPriority();  // 300

// Comparer deux niveaux
$level->isHigherThan(LoggerLevelEnum::Info); // true
```

### Niveaux par priorité croissante

| Case | Valeur | Priorité | Couleur Bootstrap |
|---|---|---|---|
| `Debug` | `debug` | 100 | `secondary` |
| `Info` | `info` | 200 | `info` |
| `Notice` | `notice` | 250 | `primary` |
| `Warning` | `warning` | 300 | `warning` |
| `Error` | `error` | 400 | `danger` |
| `Critical` | `critical` | 500 | `danger` |
