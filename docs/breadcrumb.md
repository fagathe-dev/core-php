# Breadcrumb

Génération de fil d'ariane (breadcrumb) HTML en PHP, avec détection automatique du contexte (accueil, admin, dashboard).

## Classes

| Classe | Rôle |
|---|---|
| `Breadcrumb` | Conteneur des items du fil d'ariane |
| `BreadcrumbItem` | Représente un élément individuel |
| `BreadcrumbGenerator` | Génère le HTML à partir d'un `Breadcrumb` |

## Usage de base

```php
use Fagathe\CorePhp\Breadcrumb\Breadcrumb;
use Fagathe\CorePhp\Breadcrumb\BreadcrumbItem;
use Fagathe\CorePhp\Breadcrumb\BreadcrumbGenerator;

$breadcrumb = new Breadcrumb();

$breadcrumb
    ->addItem(new BreadcrumbItem('Utilisateurs', '/users'))
    ->addItem(new BreadcrumbItem('Jean Dupont'));

$html = (new BreadcrumbGenerator($breadcrumb))->generate();
// Affiche un <nav> Bootstrap avec les items
```

## BreadcrumbItem

```php
// Item simple (texte seul)
new BreadcrumbItem('Tableau de bord');

// Item avec lien
new BreadcrumbItem('Utilisateurs', '/users');

// Item avec lien et icône (classe CSS Remix Icon)
new BreadcrumbItem('Profil', '/profile', 'ri-user-line');
```

## Breadcrumb

```php
// Avec page d'accueil automatique (défaut : true)
$breadcrumb = new Breadcrumb(homePage: true);

// Sans ajout automatique de l'accueil
$breadcrumb = new Breadcrumb(homePage: false);

// Ajouter un item à la fin
$breadcrumb->addItem(new BreadcrumbItem('Section', '/section'));

// Insérer un item au début
$breadcrumb->prependItem(new BreadcrumbItem('Racine', '/'));
```

## BreadcrumbGenerator

Le générateur utilise `RequestTrait` pour détecter automatiquement le contexte de la page et choisir l'item racine :

| Contexte | Lien racine | Label |
|---|---|---|
| URL contenant `/dashboard` | `/dashboard` | Tableau de bord |
| URL contenant `/admin` | `/admin` | Administration |
| Autre | `/` | Accueil |

```php
$generator = new BreadcrumbGenerator($breadcrumb);
$html = $generator->generate(); // Retourne le HTML ou null si $breadcrumb est null
```

## Intégration Twig

Utiliser l'extension dédiée `BreadcrumbExtension` (voir [docs/twig.md](twig.md)) :

```twig
{{ generate_breadcrumb(breadcrumb) }}
```

## HTML généré

```html
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="/">Accueil</a></li>
    <li class="breadcrumb-item"><a href="/users">Utilisateurs</a></li>
    <li class="breadcrumb-item active" aria-current="page">Jean Dupont</li>
  </ol>
</nav>
```

Les items avec une icône enveloppent le contenu dans un lien avec la classe `d-flex align-items-center` (Bootstrap).
