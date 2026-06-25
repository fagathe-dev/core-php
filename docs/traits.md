# Traits

Traits PHP réutilisables à inclure dans les services et contrôleurs. Chaque trait expose une fonctionnalité spécifique via composition.

## Liste des traits

| Trait | Rôle |
|---|---|
| `DatetimeTrait` | Manipulation des dates (timezone Paris, formatage, relatif) |
| `EntityManagerTrait` | Persistance Doctrine avec logging automatique |
| `LoggerTrait` | Journalisation automatique via `Logger` |
| `PaginationTrait` | Pagination KnpPaginator |
| `SessionFlashTrait` | Messages flash Symfony |
| `UrlGeneratorTrait` | Génération d'URL via le router Symfony |

---

## DatetimeTrait

Toutes les dates sont en timezone `Europe/Paris`.

```php
use Fagathe\CorePhp\Trait\DatetimeTrait;

class MyService
{
    use DatetimeTrait;

    public function example(): void
    {
        // Date/heure courante (DateTimeImmutable)
        $now = $this->now();

        // Formater une date
        $str = $this->formatDateTime('d/m/Y H:i', $now); // "22/06/2026 14:30"
        $str = $this->formatDateTime(); // "2026-06-22 14:30:00" (format par défaut)

        // Modifier une date
        $tomorrow  = $this->modifyDateTime('+1 day');
        $lastMonth = $this->modifyDateTime('-1 month', $someDate);

        // Convertir DateTime → DateTimeImmutable
        $immutable = $this->createFromMutable($mutableDate);

        // Comparaisons
        $this->isNewerThan($date1, $date2); // bool : $date1 > $date2
        $this->isPastDate($date);            // bool : $date < maintenant

        // Temps relatif lisible
        $this->ago($date); // "il y a 2 jours", "dans 3 heures", "il y a quelques secondes"
    }
}
```

### `getHumanDueDate()`

Catégorise une date d'échéance en `HumanDueDateEnum` :

```php
$enum = $this->getHumanDueDate($dueDate);
// HumanDueDateEnum::Overdue  → date passée
// HumanDueDateEnum::Today    → aujourd'hui
// HumanDueDateEnum::Tomorrow → demain
// HumanDueDateEnum::ThisWeek → cette semaine
// HumanDueDateEnum::NextWeek → la semaine prochaine
// HumanDueDateEnum::Later    → plus tard
```

---

## LoggerTrait

Génère automatiquement le nom de fichier de log depuis la classe hôte (`namespace/class-name`).

```php
use Fagathe\CorePhp\Trait\LoggerTrait;
use Fagathe\CorePhp\Enum\LoggerLevelEnum;

class UserService  // dans namespace App\Service
{
    use LoggerTrait;

    // Requiert que $this->security soit disponible dans la classe hôte
    // (injecté via Symfony DI)

    public function create(array $data): void
    {
        $this->generateLog(
            LoggerLevelEnum::Info,
            ['message' => 'Utilisateur créé', 'email' => $data['email']],
            ['action' => 'user.create']
        );
        // → Log dans : LOGS_DIR/service/user-service-22-06-2026.json
    }
}
```

#### Nom de fichier généré automatiquement

La convention est : `{dernier segment namespace en minuscule}/{nom classe en kebab-case}`

| Classe | Fichier de log |
|---|---|
| `App\Service\UserService` | `service/user-service` |
| `App\Controller\OrderController` | `controller/order-controller` |

#### Filename personnalisé

```php
$this->generateLog(
    LoggerLevelEnum::Warning,
    ['message' => 'Alerte critique'],
    ['action' => 'alert'],
    customFilename: 'alerts/critical' // override
);
```

---

## EntityManagerTrait

Simplifie la persistance Doctrine avec gestion d'erreurs et logs automatiques. Inclut `LoggerTrait`.

```php
use Fagathe\CorePhp\Trait\EntityManagerTrait;

class ProductService
{
    use EntityManagerTrait;

    // $this->entityManager doit être injecté (Symfony DI, readonly)

    public function create(Product $product): bool
    {
        return $this->save($product);         // persist + flush + log
    }

    public function createBatch(array $products): void
    {
        foreach ($products as $product) {
            $this->save($product, flush: false); // persist seulement
        }
        $this->entityManager->flush(); // flush manuel en fin de batch
    }

    public function delete(Product $product): bool
    {
        return $this->remove($product); // remove + flush + log
    }
}
```

> **Prérequis :** La classe hôte doit déclarer `protected readonly EntityManagerInterface $entityManager` et la propriété `$security` (pour les logs).

---

## PaginationTrait

Wrapper autour de KnpPaginatorBundle.

```php
use Fagathe\CorePhp\Trait\PaginationTrait;

class ProductRepository
{
    use PaginationTrait;

    // $this->paginator doit être injecté (Symfony DI, readonly)

    public function findPaginated(int $page, int $limit = 20): PaginationInterface
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC');

        return $this->paginate(
            queryBuilder: $qb,
            page: $page,
            limit: $limit,
            options: [
                'defaultSortFieldName'  => 'p.createdAt',
                'defaultSortDirection'  => 'DESC',
                'sortFieldWhitelist'    => ['p.name', 'p.price', 'p.createdAt'],
            ],
            action: 'product.list'
        );
    }
}
```

```twig
{# Dans un template Twig #}
{% for product in pagination %}
    {{ product.name }}
{% endfor %}

{{ knp_pagination_render(pagination) }}
```

---

## SessionFlashTrait

Ajoute un message flash à la session Symfony.

```php
use Fagathe\CorePhp\Trait\SessionFlashTrait;

class MyController
{
    use SessionFlashTrait;

    public function store(): Response
    {
        $this->addFlash('success', 'Enregistrement réussi !');
        $this->addFlash('error', 'Une erreur est survenue.');
        $this->addFlash('warning', 'Votre session expire bientôt.');

        return $this->redirect('/dashboard');
    }
}
```

```twig
{% for message in app.flashes('success') %}
    <div class="alert alert-success">{{ message }}</div>
{% endfor %}
```

---

## UrlGeneratorTrait

Génère des URLs via le router Symfony.

```php
use Fagathe\CorePhp\Trait\UrlGeneratorTrait;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MyService
{
    use UrlGeneratorTrait;

    // $this->urlGenerator doit être injecté (Symfony DI, readonly)

    public function getProfileUrl(int $userId): string
    {
        // Chemin absolu (défaut) : "/user/42/profile"
        return $this->generateUrl('user_profile', ['id' => $userId]);

        // URL complète : "https://example.com/user/42/profile"
        return $this->generateUrl(
            'user_profile',
            ['id' => $userId],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }
}
```
