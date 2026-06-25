# Http

Utilitaires pour la gestion des requêtes HTTP, sessions natives, cookies et réponses.

## Classes et traits

| Classe / Trait | Rôle |
|---|---|
| `Cookie` | Wrapper statique autour de `$_COOKIE` / `setcookie()` |
| `NativeSession` | Wrapper statique autour de `$_SESSION` |
| `RequestTrait` | Trait pour accéder aux informations de la requête HTTP |
| `ResponseTrait` | Trait pour construire des réponses HTTP structurées |

---

## Cookie

Classe statique avec un préfixe interne `__ffr.v_` appliqué automatiquement sur toutes les clés.

```php
use Fagathe\CorePhp\Http\Cookie;

// Définir un cookie (30 jours par défaut, httpOnly, SameSite=Lax)
Cookie::set('user_lang', 'fr');

// Avec des options personnalisées
Cookie::set('remember_me', '1', [
    'expires' => time() + (86400 * 365), // 1 an
    'secure'  => true,
    'path'    => '/',
]);

// Lire un cookie
$lang = Cookie::get('user_lang');           // "fr"
$lang = Cookie::get('user_lang', 'en');    // valeur par défaut si absent

// Vérifier l'existence
Cookie::has('user_lang'); // bool

// Récupérer tous les cookies
$all = Cookie::getAll(); // array<string, string>

// Supprimer un cookie
Cookie::delete('user_lang'); // bool

// Vider tous les cookies
Cookie::clear(); // bool
```

> **Note :** `set()` et `delete()` doivent être appelés **avant** tout envoi de contenu HTML (headers).

---

## NativeSession

Classe statique avec un préfixe interne `__ffr.v_`. Démarre automatiquement la session si besoin.

```php
use Fagathe\CorePhp\Http\NativeSession;

// Écrire en session
NativeSession::set('user_id', 42);
NativeSession::set('preferences', ['theme' => 'dark', 'lang' => 'fr']);

// Lire
$userId = NativeSession::get('user_id');           // 42
$userId = NativeSession::get('user_id', 0);        // valeur par défaut si absent

// Vérifier l'existence
NativeSession::has('user_id'); // bool

// Récupérer toutes les données
$all = NativeSession::getAll(); // array<string, mixed>

// Supprimer une clé
NativeSession::delete('user_id');

// Vider les données du préfixe (ne détruit pas la session)
NativeSession::clear();

// Détruire la session complètement (déconnexion)
NativeSession::destroy(); // bool
```

---

## RequestTrait

Trait à utiliser dans n'importe quelle classe pour accéder à la requête HTTP courante via `symfony/http-foundation`.

```php
use Fagathe\CorePhp\Http\RequestTrait;

class MyController
{
    use RequestTrait;

    public function index(): void
    {
        $request = $this->getRequest();           // Request Symfony
        $method  = $this->getRequestMethod();     // "GET", "POST"…
        $uri     = $this->getRequestUri();        // "/users?page=2"
        $path    = $this->getRequestPath();       // "/users"
        $origin  = $this->getOrigin();            // "https://example.com"
        $referer = $this->getRequestReferer();    // "/" ou "CLI" ou null

        // Helpers de contexte
        $isAdmin     = $this->isAdmin();          // true si path contient /admin
        $isDashboard = $this->isDashboard();      // true si path contient /dashboard
    }
}
```

---

## ResponseTrait

Trait pour construire des réponses HTTP standardisées (JSON, redirection, no content, violations).

```php
use Fagathe\CorePhp\Http\ResponseTrait;
use Symfony\Component\HttpFoundation\Response;

class MyApiController
{
    use ResponseTrait;

    public function getUser(): object
    {
        // Réponse JSON 200
        return $this->sendJson(['id' => 1, 'name' => 'Alice']);

        // Réponse JSON avec statut personnalisé
        return $this->sendJson(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
    }

    public function deleteUser(): object
    {
        // Réponse 204 No Content
        return $this->sendNoContent();
    }

    public function redirectUser(): object
    {
        // Redirection
        return $this->sendRedirection('/dashboard');
        return $this->sendRedirection('/login', Response::HTTP_MOVED_PERMANENTLY);
    }

    public function createUser(Request $request): object
    {
        $violations = $this->validator->validate($data);

        if (count($violations) > 0) {
            // Réponse 400 avec liste des erreurs de validation
            return $this->sendViolations($violations);
        }

        // ...
    }

    public function findUser(int $id): object
    {
        // Réponse 404
        return $this->notFoundResponse('Utilisateur introuvable');
    }
}
```

### Structure des réponses

Toutes les réponses retournent un objet avec les propriétés :

```php
(object) [
    'data'    => mixed,   // Données de la réponse
    'status'  => int,     // Code HTTP
    'headers' => array,   // En-têtes additionnels
    'context' => array,   // Contexte de sérialisation
]
```
