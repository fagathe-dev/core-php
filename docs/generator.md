# Generator

Génération de tokens aléatoires sécurisés et de références métier horodatées.

## Classes

| Classe | Rôle |
|---|---|
| `TokenGenerator` | Génère des tokens aléatoires cryptographiquement sécurisés |
| `RefGenerator` | Génère des références métier uniques basées sur l'horodatage |

---

## TokenGenerator

Utilise `random_int()` (cryptographiquement sécurisé) pour générer des tokens imprévisibles.

### Génération alphananumérique (défaut)

```php
use Fagathe\CorePhp\Generator\TokenGenerator;

$generator = new TokenGenerator();

// Token alphanumérique de 32 caractères (défaut)
$token = $generator->generate();
// "aZ3kR9mNpQ1xBv7wLt2Yc5hDs8FjEu6"

// Longueur personnalisée
$token = $generator->generate(64);

// Alphabet personnalisé
$token = $generator->generate(16, 'ABCDEF0123456789'); // hex uppercase
```

### Code numérique (2FA, PIN)

```php
$pin = $generator->generateNumeric(4);  // "7391"
$otp = $generator->generateNumeric(6);  // "082451"
```

### Alphabets disponibles

| Constante | Contenu |
|---|---|
| `ALPHABET_NUMERIC` | `0123456789` |
| `ALPHABET_ALPHANUMERIC` | `0-9a-zA-Z` (défaut) |

---

## RefGenerator

Génère une référence unique au format `PREFIX_YYYYMMDDHHMMSS.mmm`.
Utilise `DatetimeTrait` en interne pour la gestion de l'heure (timezone Europe/Paris).

```php
use Fagathe\CorePhp\Generator\RefGenerator;

$generator = new RefGenerator();

// Référence avec préfixe par défaut
$ref = $generator->generate();
// "REF_20251128003853.255"

// Référence avec préfixe personnalisé
$ref = $generator->generate('CMD');
// "CMD_20251128003853.255"

$ref = $generator->generate('facture');
// "FACTURE_20251128003853.255"  (automatiquement mis en majuscules)
```

### Format de la référence

```
PREFIX_YYYYMMDDHHMMSS.mmm
│       │              └── Millisecondes (3 chiffres)
│       └── Date et heure (YmdHis)
└── Préfixe en majuscules
```
