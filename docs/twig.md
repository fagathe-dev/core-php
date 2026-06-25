# Twig

Extensions Twig apportant des filtres et fonctions utilitaires pour les templates.

## Extensions disponibles

| Classe | Type | Nom Twig |
|---|---|---|
| `AppExtension` | Filtre | `typeof` |
| `BreadcrumbExtension` | Fonction | `generate_breadcrumb` |
| `CodeExtension` | Fonction | `ds_code` |
| `LogExtension` | Fonction | `generate_log` |
| `StringExtension` | Filtre | `word_wrap` |

## Enregistrement (Symfony)

Déclarer les extensions comme services taggués Twig dans `config/services.yaml` :

```yaml
services:
    Fagathe\CorePhp\Twig\AppExtension:
        tags: ['twig.extension']

    Fagathe\CorePhp\Twig\BreadcrumbExtension:
        tags: ['twig.extension']

    Fagathe\CorePhp\Twig\CodeExtension:
        tags: ['twig.extension']

    Fagathe\CorePhp\Twig\LogExtension:
        tags: ['twig.extension']

    Fagathe\CorePhp\Twig\StringExtension:
        tags: ['twig.extension']
```

---

## `typeof` — Filtre

Retourne le type PHP d'une variable (équivalent de `gettype()`).

```twig
{{ "hello" | typeof }}   {# "string" #}
{{ 42 | typeof }}        {# "integer" #}
{{ [1,2] | typeof }}     {# "array" #}
{{ null | typeof }}      {# "NULL" #}
{{ true | typeof }}      {# "boolean" #}

{# Utile pour le debug conditionnel #}
{% if variable | typeof == 'array' %}
    <ul>{% for item in variable %}<li>{{ item }}</li>{% endfor %}</ul>
{% endif %}
```

---

## `generate_breadcrumb` — Fonction

Génère le HTML du fil d'ariane Bootstrap à partir d'un objet `Breadcrumb`.

```twig
{# Dans un contrôleur, construire le breadcrumb et le passer à la vue #}
{# $breadcrumb = (new Breadcrumb())->addItem(new BreadcrumbItem('Users', '/users'))... #}

{{ generate_breadcrumb(breadcrumb) }}

{# Si breadcrumb est null, retourne une chaîne vide #}
{{ generate_breadcrumb(null) }}
```

Voir [docs/breadcrumb.md](breadcrumb.md) pour construire l'objet `Breadcrumb`.

---

## `ds_code` — Fonction

Affiche du code (HTML, Twig, PHP…) dans un template sans que celui-ci soit interprété. Les balises HTML et délimiteurs Twig sont correctement échappés.

```twig
{# Afficher un snippet Twig sans l'exécuter #}
{{ ds_code('{{ user.name }}') }}

{# Afficher un snippet HTML #}
{{ ds_code('<div class="container">Hello</div>') }}

{# Afficher un appel de fonction Twig #}
{{ ds_code('{% if user is defined %}...{% endif %}') }}
```

> Le résultat est marqué comme `is_safe: html`, donc Twig n'échappe pas la sortie une deuxième fois.

---

## `generate_log` — Fonction

Génère le rendu HTML Bootstrap (Accordion) d'une entrée de log à partir d'un objet `Log`.

```twig
{# Afficher un seul log #}
{{ generate_log(log) }}

{# Dans une liste de logs #}
<div class="accordion" id="logs-accordion">
    {% for log in logs %}
        {{ generate_log(log) }}
    {% endfor %}
</div>

{# Si log est null, retourne une chaîne vide #}
{{ generate_log(null) }}
```

Voir [docs/logger.md](logger.md) pour la création des objets `Log`.

---

## `word_wrap` — Filtre

Wrapper autour de la fonction PHP `wordwrap()`. Insère des sauts de ligne dans un texte long.

```twig
{# Largeur par défaut : 75 caractères, saut : \n #}
{{ longText | word_wrap }}

{# Largeur personnalisée #}
{{ longText | word_wrap(50) }}

{# Séparateur personnalisé (ex: <br> pour HTML) #}
{{ longText | word_wrap(80, '<br>', false) | raw }}

{# Couper les mots longs dépassant la limite #}
{{ longText | word_wrap(30, "\n", true) }}
```

| Paramètre | Défaut | Description |
|---|---|---|
| `width` | `75` | Nombre de caractères par ligne |
| `break` | `"\n"` | Chaîne insérée au saut de ligne |
| `cut_long_words` | `false` | Si `true`, coupe les mots qui dépassent la largeur |
