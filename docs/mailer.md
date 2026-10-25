# Mailer

Client HTTP du microservice **API Mailer** : envoi d'e-mails à partir de modèles prédéfinis (confirmation de compte, OTP, reset de mot de passe…), avec payloads typés, validation côté client et exceptions exploitables.

> Ce module n'envoie pas les e-mails lui-même (pas de Symfony Mailer / SMTP) : il appelle l'API Mailer, qui rend le modèle et l'envoie.

## Classes

| Classe | Rôle |
|---|---|
| `MailerClientInterface` | Contrat du client, à type-hinter pour l'autowiring |
| `MailerApiClient` | Implémentation HTTP (jeton Bearer) |
| `EmailType` | Enum des modèles d'e-mails acceptés par l'API |
| `Payload\*Payload` | Données typées d'un modèle (un payload par `EmailType`) |
| `Response\SendEmailResponse` | Réponse de l'envoi |
| `Response\HealthCheckResponse` | Réponse du health-check |
| `Exception\*` | Exceptions du module (toutes implémentent `MailerExceptionInterface`) |

## Configuration

Le client est déclaré par le bundle (`CorePhpBundle`, fichier [config/mailer_api.yaml](../config/mailer_api.yaml)). Il nécessite un service `HttpClientInterface` (fourni par FrameworkBundle avec `symfony/http-client`).

Variables d'environnement à définir dans le projet hôte :

| Variable | Rôle |
|---|---|
| `API_MAILER_BASE_URL` | URL de base de l'API Mailer (ex. `https://mailer.example.com`) |
| `API_MAILER_SECRET_KEY` | Jeton Bearer de l'application |

```dotenv
# .env
API_MAILER_BASE_URL=https://mailer.example.com

# .env.local (ou secrets:set) — ne jamais commiter le jeton
API_MAILER_SECRET_KEY=xxxxxxxx
```

Les deux variables sont optionnelles au moment de la compilation du conteneur (`cache:clear` ne casse pas si elles manquent). Leur absence n'est détectée qu'au premier appel, par une `MailerConfigurationException`, **avant** toute requête HTTP.

---

## Envoyer un e-mail

```php
use Fagathe\CorePhp\Mailer\MailerClientInterface;
use Fagathe\CorePhp\Mailer\Payload\AuthConfirmationPayload;

class RegistrationService
{
    public function __construct(
        private MailerClientInterface $mailer
    ) {}

    public function sendConfirmation(User $user, string $url): void
    {
        $response = $this->mailer->send(new AuthConfirmationPayload(
            email: $user->getEmail(),
            username: $user->getUsername(),
            confirmationUrl: $url, // doit commencer par https://
        ));

        $response->isSuccess(); // true
        $response->message;     // "E-mail envoyé avec succès."
    }
}
```

L'envoi est **synchrone** : un succès signifie que le serveur SMTP a accepté l'e-mail.

Requête envoyée : `POST {API_MAILER_BASE_URL}/api/emails/send`

```json
{
    "type": "auth_confirmation",
    "data": {
        "email": "jane.doe@gmail.com",
        "username": "jane",
        "confirmationUrl": "https://app.example.com/confirm/abc"
    }
}
```

---

## Payloads disponibles

Chaque payload valide ses données dans son constructeur et lève une `InvalidPayloadException` si elles sont invalides (aucun appel à l'API n'est fait). Les champs optionnels laissés à `null` ne sont pas envoyés.

| Payload | `EmailType` | Champs obligatoires | Champs optionnels |
|---|---|---|---|
| `AuthConfirmationPayload` | `auth_confirmation` | `email`, `username`, `confirmationUrl` (https) | — |
| `AuthOtpPayload` | `auth_otp` | `email`, `code` (4 à 12 caractères) | `username`, `actionLabel`, `expiresIn` |
| `AuthResetPasswordPayload` | `auth_reset_password` | `email`, `username`, `resetUrl` | — |
| `AuthProfileChangeEmailPayload` | `auth_profile_change_email` | `username`, `newEmail`, `confirmationUrl` (https) | — |
| `AdminAccountCreatedPayload` | `admin_account_created` | `email`, `username`, `plainPassword`, `roles`, `loginUrl` | `changePasswordUrl`, `createdBy` (défaut `dashboard`) |
| `ContactRecapPayload` | `contact_recap` | `email`, `name`, `subject`, `message` | `senderEmail`, `phone` |

### Exemples

```php
use Fagathe\CorePhp\Mailer\Payload\AuthOtpPayload;
use Fagathe\CorePhp\Mailer\Payload\AuthProfileChangeEmailPayload;
use Fagathe\CorePhp\Mailer\Payload\AdminAccountCreatedPayload;
use Fagathe\CorePhp\Mailer\Payload\ContactRecapPayload;

// Code OTP : toujours une chaîne (un nombre JSON est rejeté par l'API)
$this->mailer->send(new AuthOtpPayload(
    email: 'jane.doe@gmail.com',
    code: '482910',
    username: 'jane',
    actionLabel: 'Connexion',
    expiresIn: '10 minutes',
));

// Changement d'e-mail : le destinataire est la NOUVELLE adresse (clé "newEmail")
$this->mailer->send(new AuthProfileChangeEmailPayload(
    username: 'jane',
    newEmail: 'jane.new@gmail.com',
    confirmationUrl: 'https://app.example.com/profile/confirm-email/abc',
));

// Compte créé par un admin : roles accepte une chaîne ou une liste
$this->mailer->send(new AdminAccountCreatedPayload(
    email: 'john@example.com',
    username: 'john',
    plainPassword: $plainPassword,
    roles: ['ROLE_USER', 'ROLE_EDITOR'],
    loginUrl: 'https://app.example.com/login',
    changePasswordUrl: 'https://app.example.com/profile/password',
));

// Récapitulatif d'un formulaire de contact, envoyé au visiteur
$this->mailer->send(new ContactRecapPayload(
    email: $data['email'],
    name: $data['name'],
    subject: $data['subject'],
    message: $data['message'],
    phone: $data['phone'] ?? null,
));
```

### Données sensibles

- `AuthOtpPayload::$code` et `AdminAccountCreatedPayload::$plainPassword` sont marqués `#[\SensitiveParameter]` : ils n'apparaissent pas dans les stack traces.
- `AdminAccountCreatedPayload` masque le mot de passe dans `var_dump()` / `dump()` (`********`).
- Le mot de passe part en clair dans l'e-mail : préférer renseigner `changePasswordUrl` pour inviter l'utilisateur à le changer.

---

## Health-check

```php
$health = $this->mailer->healthCheck(); // GET /api/health-check

$health->isOk();     // true si status === "ok"
$health->service;    // "mailer-app"
$health->timestamp;  // ?DateTimeImmutable
```

---

## Gestion des erreurs

### Hiérarchie

```
MailerExceptionInterface (attraper toutes les erreurs du module)
├── InvalidPayloadException        données invalides, levée à la construction du payload
├── MailerConfigurationException   API_MAILER_BASE_URL ou API_MAILER_SECRET_KEY vide
├── MailerTransportException       API injoignable (DNS, connexion, timeout)
└── MailerApiException             réponse d'erreur de l'API (code HTTP ≥ 300)
    ├── MailerAuthenticationException   401 : jeton absent, invalide ou révoqué
    └── MailerValidationException       422 : données refusées / destinataire injoignable
```

### Informations exposées par `MailerApiException`

| Méthode | Contenu |
|---|---|
| `getStatusCode()` | Code HTTP de la réponse |
| `getErrorCode()` | Champ `error` de la réponse (ex. `recipient_rejected`) |
| `getDetails()` | Champ `details` (message technique) |
| `getViolations()` | Erreurs 422 : `['champ' => 'message']` |
| `isRetryable()` | `true` si l'erreur est temporaire |

### Quand réessayer ?

| Cas | `isRetryable()` |
|---|---|
| 502 (erreur SMTP / transport côté mailer) | `true` |
| 503 | `true`, sauf `mailer_dsn_not_configured` et `mailer_dsn_invalid` |
| 401, 422, autres codes | `false` |
| `MailerTransportException` | `true` ⚠️ |

> ⚠️ Après un timeout, l'e-mail a pu être envoyé malgré tout : réessayer peut créer un doublon.

### Exemple

```php
use Fagathe\CorePhp\Mailer\Exception\InvalidPayloadException;
use Fagathe\CorePhp\Mailer\Exception\MailerApiException;
use Fagathe\CorePhp\Mailer\Exception\MailerConfigurationException;
use Fagathe\CorePhp\Mailer\Exception\MailerTransportException;
use Fagathe\CorePhp\Mailer\Exception\MailerValidationException;

try {
    $this->mailer->send(new AuthResetPasswordPayload($email, $username, $resetUrl));

} catch (InvalidPayloadException $e) {
    // Bug côté appelant : champ vide, URL non https, code OTP de mauvaise longueur…

} catch (MailerValidationException $e) {
    // 422 : afficher $e->getViolations() ou traiter $e->getErrorCode()
    //       ("email_recipient_error" / "recipient_rejected")

} catch (MailerApiException|MailerTransportException $e) {
    if ($e->isRetryable()) {
        // Remettre en file d'attente (Messenger…) plutôt que réessayer en boucle
    }
    // Avec LoggerTrait (voir docs/traits.md)
    $this->generateLog(LoggerLevelEnum::Error, [
        'message' => 'Échec envoi e-mail',
        'exception' => $e->getMessage(),
    ]);

} catch (MailerConfigurationException $e) {
    // Variable d'environnement manquante : erreur de déploiement
    throw $e;
}
```

---

## Tests

Dans les tests du projet hôte, injecter un `MockHttpClient` pour ne pas appeler l'API :

```php
use Fagathe\CorePhp\Mailer\MailerApiClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

$response = new JsonMockResponse(['status' => 'success', 'message' => 'E-mail envoyé avec succès.']);

$client = new MailerApiClient(
    new MockHttpClient($response),
    'https://mailer.test',
    'test-token',
);

$client->send(new AuthOtpPayload('jane.doe@gmail.com', '482910'));

$response->getRequestUrl();          // "https://mailer.test/api/emails/send"
$response->getRequestOptions()['body']; // JSON envoyé
```

Ou mocker directement `MailerClientInterface`.
