# Mailer

Socle **agnostique** d'envoi d'e-mails transactionnels au-dessus de Symfony Mailer : une classe abstraite qui gère l'envoi, les logs et les exceptions, plus un layout et des composants Twig réutilisables.

> core-php ne définit **aucun** e-mail. Chaque projet déclare les siens (DTO, handler, template) : le récapitulatif de contact du portfolio vit dans le portfolio.

## Contenu du module

| Élément | Rôle |
|---|---|
| `Mailer\AbstractMailerHandler` | Base d'un handler : validation du DTO, contrôle des destinataires, expéditeur et contexte par défaut, envoi, logs, exceptions |
| `Mailer\EmailDeliverabilityChecker` | Vérifie (DNS MX / A) que le domaine du destinataire reçoit des e-mails |
| `Mailer\Exception\*` | Exceptions du module, toutes `MailerExceptionInterface` |
| `templates/layout.html.twig` | Layout HTML générique (`@CorePhp/layout.html.twig`) |
| `templates/components/_*.html.twig` | Composants (`@CorePhp/components/…`) |
| `config/my_email.yaml` | Paramètres `core_php.email.*` |

Prérequis dans le projet hôte : `symfony/mailer` configuré (`MAILER_DSN`), `symfony/twig-bundle`, `symfony/validator` (optionnel mais recommandé).

## Configuration

Toutes les variables sont optionnelles ; une valeur absente masque l'élément correspondant.

| Variable d'environnement | Paramètre | Effet |
|---|---|---|
| `APP_NAME` | `core_php.email.app_name` | Nom de l'expéditeur, pied de page (`app_name`) |
| `DEFAULT_EMAIL_SENDER` | `core_php.email.default_sender` | Adresse d'expédition par défaut |
| `DEFAULT_URI` | `core_php.email.app_uri` | URL de l'application, pour rendre le logo absolu |
| `LOGO_PATH` | `core_php.email.logo_path` | Chemin du logo (relatif à `DEFAULT_URI`) ou URL absolue ; défaut `images/logo.webp` |
| `SUPPORT_EMAIL` | `core_php.email.support_email` | Bloc « Besoin d'aide ? » du pied de page |
| — | `core_php.email.check_deliverability` | `true` par défaut ; `false` pour couper le contrôle DNS (tests, hors ligne) |

```yaml
# config/services.yaml du projet hôte
when@test:
    parameters:
        core_php.email.check_deliverability: false
```

---

## Créer un e-mail dans le projet hôte

Un e-mail = un **DTO** (les données), un **handler** (la description de l'e-mail), un **template**.

### 1. DTO

```php
// src/Mail/Dto/ContactRecapDto.php
namespace App\Mail\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ContactRecapDto
{
    public function __construct(
        #[Assert\NotBlank, Assert\Email]
        public string $email,
        #[Assert\NotBlank]
        public string $fullName,
        #[Assert\Length(min: 10, max: 2000)]
        public string $message,
        public ?string $subjectLabel = null,
    ) {}
}
```

Les contraintes sont vérifiées avant l'envoi (`InvalidEmailDataException`).

### 2. Handler

```php
// src/Mail/ContactRecapHandler.php
namespace App\Mail;

use App\Mail\Dto\ContactRecapDto;
use Fagathe\CorePhp\Mailer\AbstractMailerHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Address;

/**
 * @extends AbstractMailerHandler<ContactRecapDto>
 */
final class ContactRecapHandler extends AbstractMailerHandler
{
    // Le constructeur est libre : les dépendances du socle sont injectées à part.
    public function __construct(
        #[Autowire('%env(MAILER_OWNER_ADDRESS)%')]
        private readonly string $ownerEmail,
    ) {}

    protected function getTemplate(): string
    {
        return 'emails/contact_recap.html.twig';
    }

    protected function getSubject(object $dto): string
    {
        return 'Votre message a bien été reçu';
    }

    protected function getRecipients(object $dto): array
    {
        return [new Address($dto->email, $dto->fullName)];
    }

    // Optionnel : ['dto' => $dto] par défaut. Ne pas utiliser la clé "email" (réservée par Symfony).
    protected function getContext(object $dto): array
    {
        return ['dto' => $dto, 'submittedAt' => new \DateTimeImmutable()];
    }

    // Optionnel : aucun Reply-To par défaut.
    protected function getReplyTo(object $dto): ?Address
    {
        return new Address($this->ownerEmail);
    }
}
```

| Méthode | Obligatoire | Défaut |
|---|---|---|
| `getTemplate()` | oui | — |
| `getSubject($dto)` | oui | — |
| `getRecipients($dto)` | oui | — (`Address` ou chaîne) |
| `getContext($dto)` | non | `['dto' => $dto]` |
| `getReplyTo($dto)` | non | aucun |
| `getFrom($dto)` | non | `core_php.email.default_sender`, au nom de `core_php.email.app_name` |

Le handler est un service autowiré du projet hôte : rien à déclarer. Ses dépendances de base (mailer, validator, paramètres) arrivent par `setMailerDependencies()` (`#[Required]`).

### 3. Template

```twig
{# templates/emails/contact_recap.html.twig #}
{% extends '@CorePhp/layout.html.twig' %}

{% block content %}
  {% include '@CorePhp/components/_text.html.twig' with {
    level: 'h1',
    content: 'Merci ' ~ (dto.fullName|e) ~ ' !'
  } only %}

  {% include '@CorePhp/components/_box.html.twig' with {
    content: dto.message|e|nl2br
  } only %}
{% endblock %}
```

Le contexte contient toujours `app_name`, `logo_url` et `support_email` (lus par le layout), en plus de `getContext()`.

### 4. Envoi

```php
public function __construct(private ContactRecapHandler $contactRecap) {}

$this->contactRecap->send(new ContactRecapDto(
    email: $form->email,
    fullName: $form->fullName,
    message: $form->message,
));
```

`createEmail($dto)` construit l'e-mail sans l'envoyer (prévisualisation, tests).

---

## Layout et composants

### Layout (`@CorePhp/layout.html.twig`)

| Bloc | Contenu par défaut |
|---|---|
| `logo` | Logo centré, si `logo_url` est défini |
| `content` | Corps de l'e-mail (à remplir) |
| `footer` | « Besoin d'aide ? » si `support_email` est défini, puis © année + `app_name` |

### Composants (`@CorePhp/components/…`)

À inclure avec `with { … } only`. Tous acceptent `custom_style` (CSS inline ajouté).

| Composant | Props |
|---|---|
| `_text.html.twig` | `level` (h1–h6, p, small), `content`, `color`, `size`, `align` |
| `_button.html.twig` | `url`, `label`, `bg_color`, `text_color` |
| `_alert.html.twig` | `type` (success, danger, warning, info), `title`, `content` |
| `_box.html.twig` | `content`, `background`, `padding`, `radius` |
| `_card.html.twig` | `content`, `background`, `padding` |
| `_section.html.twig` | `content`, `bg_color`, `padding`, `align` |
| `_link.html.twig` | `url`, `label`, `color` |
| `_list.html.twig` | `items` (tableau), `bullet_color` |
| `_divider.html.twig` | `color`, `height` |

> ⚠️ `content` est affiché en `|raw` (HTML autorisé). Échapper toute donnée utilisateur avant de la concaténer : `'Bonjour ' ~ (dto.name|e)`, `url|e('html_attr')`.

### Surcharger

Pour adapter le layout ou un composant à un projet : `templates/bundles/CorePhpBundle/layout.html.twig` (ou `…/components/_button.html.twig`).

---

## Gestion des erreurs

```
MailerExceptionInterface
├── InvalidEmailDataException       DTO invalide : getViolations() → ['propriété' => 'message']
├── RecipientUnreachableException   domaine du destinataire injoignable : getRecipientEmail()
└── EmailSendingException           échec du transport : isRecipientRejected(), isRetryable()
```

Toute autre erreur levée par Symfony Mailer (template introuvable, configuration…) est journalisée puis relancée telle quelle.

```php
use Fagathe\CorePhp\Mailer\Exception\EmailSendingException;
use Fagathe\CorePhp\Mailer\Exception\InvalidEmailDataException;
use Fagathe\CorePhp\Mailer\Exception\RecipientUnreachableException;

try {
    $this->contactRecap->send($dto);
} catch (InvalidEmailDataException $e) {
    // Bug côté appelant, ou formulaire à revalider : $e->getViolations()
} catch (RecipientUnreachableException $e) {
    // Adresse saisie inexploitable : le signaler à l'utilisateur
} catch (EmailSendingException $e) {
    if ($e->isRetryable()) {
        // Serveur indisponible : réessayer plus tard (Messenger…)
    }
    // Sinon le destinataire a été refusé (SMTP 55x)
}
```

> Si `Symfony\Component\Mailer\Messenger\SendEmailMessage` est routé vers un transport asynchrone, `send()` ne fait que mettre l'e-mail en file : les erreurs de transport surviennent dans le worker, pas dans `send()`.

## Logs

Via `LoggerTrait` : un fichier par handler, `logs/<dernier segment du namespace>/<handler-en-kebab>-jj-mm-aaaa.json` (ex. `logs/mail/contact-recap-handler-26-10-2026.json`).

| `action` | Niveau | Quand |
|---|---|---|
| `mailer.send_success` | info | e-mail envoyé |
| `mailer.validation_failed` | warning | DTO invalide (violations seulement) |
| `mailer.recipient_unreachable` | error | domaine injoignable |
| `mailer.send_error` | error | échec du transport ou autre erreur |

Les logs contiennent le handler, le template, l'objet et les destinataires, **jamais** le contenu du DTO (jetons, mots de passe…).

## Tests dans le projet hôte

Avec `MAILER_DSN=null://null` et `core_php.email.check_deliverability: false`, un `KernelTestCase` / `WebTestCase` peut utiliser les assertions mailer de Symfony :

```php
static::getContainer()->get(ContactRecapHandler::class)->send($dto);

self::assertEmailCount(1);
self::assertEmailHtmlBodyContains(self::getMailerMessage(), 'Merci Jane');
```
