# Uploader

Upload de fichiers vers le dossier `public/`, avec validation MIME/taille, sécurisation du nom et résultat structuré.

## Classes

| Classe | Rôle |
|---|---|
| `UploaderService` | Upload physique du fichier, déplacement et suppression |
| `UploaderValidationService` | Validation du type MIME et de la taille |
| `UploadResult` | DTO contenant les métadonnées du fichier uploadé |
| `FileUploadException` | Exception levée en cas d'échec d'upload |

## Configuration

Les deux services sont déclarés par le bundle (`CorePhpBundle`, fichier [config/my_uploader.yaml](../config/my_uploader.yaml)) : il suffit de les injecter, aucune déclaration dans `config/services.yaml` n'est nécessaire.

> Le bundle est enregistré automatiquement par Flex (`"type": "symfony-bundle"`). Sinon, l'ajouter à la main dans `config/bundles.php` :
> `Fagathe\CorePhp\CorePhpBundle::class => ['all' => true],`

### Paramètres

| Paramètre | Défaut | Rôle |
|---|---|---|
| `core_php.uploader.public_dir` | `public` | Dossier public, relatif à `%kernel.project_dir%` |
| `core_php.uploader.upload_dir` | `uploads` | Dossier racine des uploads dans le dossier public |
| `core_php.uploader.max_filesize` | `52428800` (50 Mo) | Taille max en octets, utilisée par `UploaderValidationService` |
| `core_php.uploader.rename_file` | `true` | Ajoute un suffixe unique (`uniqid()`) au nom du fichier |

Les types MIME autorisés par défaut viennent de `MimeType::getSupportedMimetypes()` (voir [docs/file.md](file.md)) ; ils ne sont pas paramétrables en YAML, utiliser `setAllowedMimeTypes()`.

Pour changer une valeur, surcharger le paramètre dans le projet hôte :

```yaml
# config/services.yaml
parameters:
    core_php.uploader.upload_dir: 'media'
    core_php.uploader.max_filesize: 10485760 # 10 Mo
```

### Constantes legacy

Les constantes `PUBLIC_DIR`, `UPLOAD_DIR`, `UPLOAD_MAX_FILESIZE`, `UPLOAD_RENAME_FILE` et `UPLOAD_SUPPORTED_MIMES` de `files/constants.php` sont **dépréciées**. Elles ne servent plus que de valeurs de repli quand les services sont instanciés hors du conteneur Symfony (argument laissé à `null`).

---

## UploaderService

### Upload d'un fichier

```php
use Fagathe\CorePhp\Uploader\UploaderService;
use Fagathe\CorePhp\Uploader\FileUploadException;

class UserController
{
    public function __construct(
        private UploaderService $uploader
    ) {}

    public function uploadAvatar(Request $request): Response
    {
        $file = $request->files->get('avatar'); // UploadedFile

        try {
            $result = $this->uploader
                ->setUploadDirectory('avatars')  // sous-dossier dans uploads/
                ->upload($file);

            // Stocker $result->relativePath en BDD
            // ex: "uploads/avatars/alice-6673abc12.jpg"
            $user->setAvatar($result->relativePath);

        } catch (FileUploadException $e) {
            // Gérer l'erreur
        }
    }
}
```

### Nommage des fichiers

Le nom d'origine est toujours « sluggifié » et mis en minuscules, puis :

| `rename_file` | Fichier `Ma Photo.JPG` |
|---|---|
| `true` (défaut) | `ma-photo-6673abc12.jpg` |
| `false` | `ma-photo.jpg` |

> ⚠️ Avec `rename_file: false`, un fichier du même nom déjà présent dans le dossier est **écrasé**.

### Remplacer un fichier existant

```php
$result = $this->uploader
    ->setUploadDirectory('avatars')
    ->upload($newFile, $user->getAvatar()); // Supprime l'ancien fichier automatiquement
```

### Supprimer un fichier

```php
$this->uploader->delete($user->getAvatar()); // bool : true si le fichier a été supprimé
// Ex: supprime public/uploads/avatars/alice-6673abc12.jpg
```

Le chemin est relatif au dossier public et doit désigner un **fichier** du dossier d'upload (`core_php.uploader.upload_dir`). Sinon `delete()` ne supprime rien et retourne `false`, sans exception :

| Chemin reçu | Résultat |
|---|---|
| `uploads/avatars/alice.jpg` (existant) | supprimé, `true` |
| `uploads/avatars/absent.jpg` | `false` |
| `../.env`, `uploads/../../.env` | `false` (remontée `..` refusée) |
| `index.php` | `false` (hors dossier d'upload) |
| `uploads`, `uploads/avatars` | `false` (dossier) |
| lien symbolique vers un fichier hors `uploads/` | `false` |

Le même contrôle s'applique au 2ᵉ argument de `upload($file, $oldFilename)` : un ancien chemin invalide est ignoré, l'upload réussit quand même.

> Le chemin attendu est le chemin relatif stocké par l'application (`UploadResult::$relativePath`) : une URL absolue doit d'abord être convertie en chemin relatif.

### Instanciation hors Symfony DI

```php
$uploader = new UploaderService(
    security:   $security,
    slugger:    $slugger,
    projectDir: '/var/www/app',
    publicDir:  'public',   // null → constante PUBLIC_DIR, sinon 'public'
    uploadDir:  'uploads',  // null → constante UPLOAD_DIR, sinon 'uploads'
    renameFile: true,       // null → constante UPLOAD_RENAME_FILE, sinon true
);
```

---

## UploaderValidationService

À utiliser **avant** l'upload pour valider le fichier.

```php
use Fagathe\CorePhp\Uploader\UploaderValidationService;

public function __construct(
    private UploaderValidationService $validator
) {}

// Types MIME de MimeType::getSupportedMimetypes() et taille de core_php.uploader.max_filesize
$result = $this->validator->validate($uploadedFile);

if ($result === true) {
    // Fichier valide → procéder à l'upload
} else {
    // $result est un tableau d'erreurs
    foreach ($result as $error) {
        echo $error; // "Taille limite dépassée (50 Mo max)."
                     // "Format de fichier non supporté."
    }
}
```

### Configuration personnalisée

```php
$this->validator
    ->setAllowedMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
    ->setMaxSize(2 * 1024 * 1024); // 2 Mo

$result = $this->validator->validate($file);
```

> ⚠️ Le service injecté est partagé : les setters modifient l'instance pour le reste de la requête. Si plusieurs règles coexistent dans une même requête, utiliser une instance dédiée :
> `new UploaderValidationService(['image/jpeg', 'image/png'], 2 * 1024 * 1024)`

Hors conteneur, `new UploaderValidationService()` sans argument utilise `MimeType::getSupportedMimetypes()` et la constante `UPLOAD_MAX_FILESIZE` (50 Mo si absente).

---

## UploadResult — DTO de retour

`UploaderService::upload()` retourne un `UploadResult` (lecture seule) avec :

```php
$result = $this->uploader->upload($file);

$result->relativePath;  // "uploads/avatars/alice-6673abc12.jpg"  ← à stocker en BDD
$result->originalName;  // "ma-photo.jpg"
$result->newName;        // "ma-photo-6673abc12.jpg"
$result->size;           // 204800  (octets)
$result->mimeType;       // "image/jpeg"
$result->extension;      // "jpg"
```

---

## Workflow complet recommandé

```php
public function __construct(
    private UploaderService $uploader,
    private UploaderValidationService $validator,
) {}

public function upload(Request $request): Response
{
    $file = $request->files->get('document');

    if (!$file) {
        return $this->sendJson(['error' => 'Aucun fichier'], 400);
    }

    // 1. Valider
    $validation = $this->validator->validate($file);

    if ($validation !== true) {
        return $this->sendJson(['errors' => $validation], 422);
    }

    // 2. Uploader
    try {
        $result = $this->uploader
            ->setUploadDirectory('documents')
            ->upload($file, $entity->getDocument());

        $entity->setDocument($result->relativePath);
        $this->save($entity);

        return $this->sendJson(['path' => $result->relativePath]);

    } catch (FileUploadException $e) {
        return $this->sendJson(['error' => $e->getMessage()], 500);
    }
}
```
