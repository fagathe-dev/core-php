# Guide de Migration - Système de Logging

## 📋 Changements Apportés (v2.0)

### 1. **Origin déplacé dans Context**

- ✅ **Avant** : `origin` était une propriété séparée au niveau racine du log
- ✅ **Maintenant** : `origin` fait partie du `context`

### 2. **Structure des Logs Modifiée**

#### Ancienne Structure (v1.0)

```json
{
    "id": "LOG_xxx",
    "level": "info",
    "content": {...},
    "context": {...},
    "timestamp": "2025-12-06 14:30:25",
    "origin": "security"
}
```

#### Nouvelle Structure (v2.0)

```json
{
    "id": "LOG_xxx",
    "level": "info",
    "content": {...},
    "context": {
        ...
        "origin": "security"
    },
    "timestamp": "2025-12-06 14:30:25"
}
```

### 3. **Support Amélioré pour les Appels API**

Structure recommandée pour logger les appels API externes (cURL, Guzzle, etc.) :

```php
$logger->info(
    [
        'message' => 'Appel API externe',
        'request' => [
            'body' => $requestBody,
            'headers' => $requestHeaders
        ],
        'response' => $apiResponse
    ],
    [
        'url' => 'https://api.example.com/endpoint',
        'method' => 'POST',
        'action' => 'api.external.call',
        'origin' => null  // null pour appels externes
    ]
);
```

---

## ✅ Compatibilité avec le Code Existant

### **Aucune Modification Requise !**

Le code existant continue de fonctionner sans changement grâce à la rétrocompatibilité intégrée :

```php
// ✅ Ceci continue de fonctionner exactement comme avant
$logger->info([
    'message' => 'Utilisateur connecté',
    'user_id' => $user->getId()
]);

// ✅ Les contextes personnalisés fonctionnent toujours
$logger->error(
    ['message' => 'Erreur de connexion'],
    ['action' => 'database.connection.failed']
);
```

### Pourquoi Aucun Changement n'est Nécessaire ?

1. Le `Logger` ajoute **automatiquement** `origin` dans le contexte
2. Les méthodes de logging (`info()`, `error()`, etc.) n'ont pas changé
3. La structure des paramètres `$content` et `$context` reste identique

---

## 🆕 Nouvelles Fonctionnalités

### 1. **Origin Personnalisé**

Vous pouvez maintenant surcharger l'origin par défaut :

```php
// Origin automatique (nom du fichier de log)
$logger->info(['message' => 'Action normale']);
// → context.origin = "security"

// Origin personnalisé
$logger->info(
    ['message' => 'Action spéciale'],
    ['origin' => 'external-api']
);
// → context.origin = "external-api"

// Origin null (appels externes)
$logger->info(
    ['message' => 'Appel API tiers'],
    ['origin' => null]
);
// → context.origin = null
```

### 2. **Logging d'Appels API avec Structure Complète**

Pour les appels API, utilisez cette structure standardisée :

```php
// Exemple : Appel à une API externe
$requestBody = ['email' => 'user@example.com', 'name' => 'John Doe'];
$requestHeaders = [
    'Content-Type' => 'application/json',
    'Authorization' => 'Bearer ' . $token
];

$response = $this->makeApiCall($url, $requestBody, $requestHeaders);

$logger->info(
    [
        'message' => 'Appel API externe effectué',
        'request' => [
            'body' => $requestBody,
            'headers' => $requestHeaders  // Tokens seront automatiquement masqués
        ],
        'response' => $response
    ],
    [
        'url' => 'https://api.external.com/v1/users',
        'method' => 'POST',
        'action' => 'api.external.user.create',
        'origin' => null
    ]
);
```

**Résultat dans le fichier JSON :**

```json
{
  "id": "LOG_20251206143530_674f2b42c8f1a",
  "level": "info",
  "content": {
    "message": "Appel API externe effectué",
    "request": {
      "body": {
        "email": "user@example.com",
        "name": "John Doe"
      },
      "headers": {
        "Content-Type": "application/json",
        "Authorization": "Bearer ***"
      }
    },
    "response": {
      "status": "success",
      "user_id": "12345"
    }
  },
  "context": {
    "url": "https://api.external.com/v1/users",
    "method": "POST",
    "action": "api.external.user.create",
    "origin": null
  },
  "timestamp": "2025-12-06 14:35:30"
}
```

### 3. **URL et Method Personnalisés**

Utile pour les contextes CLI ou les tâches en arrière-plan :

```php
// Dans une commande Symfony ou un job en arrière-plan
$logger->info(
    ['message' => 'Traitement en arrière-plan'],
    [
        'url' => 'cli://commands/process-queue',
        'method' => 'CLI',
        'action' => 'queue.process'
    ]
);
```

---

## 📝 Exemples d'Utilisation par Cas

### Cas 1 : Logging Standard (Aucun Changement)

```php
class UserService
{
    private Logger $logger;

    public function __construct(RequestStack $requestStack, Security $security)
    {
        $this->logger = new Logger('user-service', $requestStack, $security);
    }

    public function createUser(array $data): User
    {
        // ✅ Utilisation standard - aucun changement requis
        $this->logger->info([
            'message' => 'Création d\'utilisateur',
            'username' => $data['username'],
            'email' => $data['email']
        ]);

        // ... code de création ...

        $this->logger->info(
            ['message' => 'Utilisateur créé avec succès', 'user_id' => $user->getId()],
            ['action' => 'user.created']
        );

        return $user;
    }
}
```

### Cas 2 : Appel API avec cURL

```php
class ExternalApiService
{
    private Logger $logger;

    public function __construct(RequestStack $requestStack, Security $security)
    {
        // Logger configuré sans IP logging pour éviter les boucles
        $this->logger = new Logger('external-api', $requestStack, $security, false);
    }

    public function createExternalUser(array $userData): array
    {
        $url = 'https://api.external.com/v1/users';
        $requestBody = [
            'email' => $userData['email'],
            'name' => $userData['name']
        ];
        $requestHeaders = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiToken
        ];

        // Appel cURL
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestBody),
            CURLOPT_HTTPHEADER => array_map(
                fn($k, $v) => "$k: $v",
                array_keys($requestHeaders),
                $requestHeaders
            ),
            CURLOPT_RETURNTRANSFER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $responseData = json_decode($response, true);

        // ✅ Nouveau : Logging structuré de l'appel API
        $this->logger->info(
            [
                'message' => 'Appel API externe pour création d\'utilisateur',
                'request' => [
                    'body' => $requestBody,
                    'headers' => $requestHeaders  // Token masqué automatiquement
                ],
                'response' => [
                    'http_code' => $httpCode,
                    'body' => $responseData
                ]
            ],
            [
                'url' => $url,
                'method' => 'POST',
                'action' => 'api.external.user.create',
                'origin' => null  // Appel externe, pas d'origin spécifique
            ]
        );

        return $responseData;
    }
}
```

### Cas 3 : Commande CLI

```php
class ProcessQueueCommand extends Command
{
    private Logger $logger;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initLogger();

        $this->logger->info(
            ['message' => 'Démarrage du traitement de la queue'],
            [
                'action' => 'queue.process.start',
                'url' => 'cli://commands/process-queue',
                'method' => 'CLI'
            ]
        );

        // ... traitement ...

        $this->logger->info(
            [
                'message' => 'Traitement de la queue terminé',
                'processed_items' => $count,
                'duration_seconds' => $duration
            ],
            ['action' => 'queue.process.complete']
        );

        return Command::SUCCESS;
    }

    private function initLogger(): void
    {
        $this->logger = new Logger(
            'command/process-queue',
            $this->requestStack,
            $this->security,
            false  // Pas d'IP en mode CLI
        );
    }
}
```

### Cas 4 : Event Listener (Aucun Changement)

```php
class SecurityEventListener
{
    private Logger $logger;

    public function __construct(RequestStack $requestStack, Security $security)
    {
        $this->logger = new Logger('security-events', $requestStack, $security);
    }

    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        // ✅ Utilisation standard - aucun changement
        $this->logger->info([
            'message' => 'Connexion réussie',
            'user_id' => $user->getUserIdentifier(),
            'user_roles' => $user->getRoles()
        ]);
    }
}
```

---

## 🔒 Sécurité et Confidentialité

### Masquage Automatique des Données Sensibles

Le système masque automatiquement les valeurs contenant ces mots-clés :

- `password`, `pass`, `pwd`
- `token`, `api_key`
- `secret`, `credential`
- `auth`, `bearer`
- `iban`, `rib`, `card`, `cvv`

**Exemple :**

```php
$logger->info([
    'message' => 'Authentification API',
    'api_token' => 'secret_key_12345',
    'user_password' => 'MyPassword123'
]);

// ✅ Dans le log JSON :
// {
//   "content": {
//     "message": "Authentification API",
//     "api_token": "***************",
//     "user_password": "***************"
//   }
// }
```

### Protection Contre les Boucles Infinies

⚠️ **Important** : Lors de la création d'un Logger pour des services qui sont utilisés par le Logger
lui-même (comme `IPChecker`), toujours définir `$logIP = false` :

```php
// ✅ Correct - Évite les boucles infinies
$this->logger = new Logger('ip-checker/find-ip', $requestStack, $security, false);

// ❌ Incorrect - Risque de boucle infinie
$this->logger = new Logger('ip-checker/find-ip', $requestStack, $security, true);
```

---

## 📊 Vérification des Logs Après Migration

Pour vérifier que vos logs sont correctement générés :

1. **Consulter les fichiers de logs** :

   ```bash
   ls -la logs/
   cat logs/security-06-12-2025.json | jq
   ```

2. **Vérifier la structure** :

   ```bash
   # Vérifier que origin est dans context
   cat logs/security-06-12-2025.json | jq '.[] | select(.context.origin)'

   # Compter les logs par origin
   cat logs/security-06-12-2025.json | jq '.[] | .context.origin' | sort | uniq -c
   ```

3. **Vérifier les appels API** :
   ```bash
   # Logs avec request.body et request.headers
   cat logs/external-api-06-12-2025.json | jq '.[] | select(.content.request)'
   ```

---

## 🐛 Dépannage

### Problème : Origin n'apparaît pas dans les logs

**Solution** : Vérifiez que vous utilisez la dernière version du Logger et que le fichier `Log.php`
contient `origin` dans `CONTEXT_KEYS`.

### Problème : Boucle infinie lors du logging

**Solution** : Vérifiez que `$logIP = false` pour les services utilisés par le Logger (IPChecker,
etc.).

### Problème : Les tokens ne sont pas masqués

**Solution** : Assurez-vous que les clés contiennent l'un des mots-clés sensibles : `token`,
`secret`, `password`, `auth`, etc.

---

## 📚 Ressources Supplémentaires

- Documentation complète : `src/Service/Logger/doc.md`
- Exemples de code : Voir les implémentations dans `src/Controller/`, `src/Command/`,
  `src/EventListener/`
- Logs de rotation : `JsonLoggerRotation.php`

---

**Version du Guide** : 2.0  
**Date de Mise à Jour** : 6 décembre 2025  
**Auteur** : Journal App
