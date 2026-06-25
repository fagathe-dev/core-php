# Uploader

Upload de fichiers vers le dossier `public/`, avec validation MIME/taille, sécurisation du nom et résultat structuré.

## Classes

| Classe | Rôle |
|---|---|
| `UploaderService` | Upload physique du fichier, déplacement et suppression |
| `UploaderValidationService` | Validation du type MIME et de la taille |
| `UploadResult` | DTO contenant les métadonnées du fichier uploadé |
| `FileUploadException` | Exception levée en cas d'échec d'upload |

## Prérequis

Les constantes suivantes doivent être définies :

```php
define('PUBLIC_DIR', 'public');           // Nom du dossier public (défaut si absent)
define('UPLOAD_DIR', 'uploads');          // Dossier racine des uploads dans public/
define('UPLOAD_SUPPORTED_MIMES', [...]);  // Tableau de types MIME autorisés
define('UPLOAD_MAX_FILESIZE', 5242880);   // Taille max en octets (ici 5 Mo)
```

---

## UploaderService

### Configuration via Symfony DI

```yaml
# config/services.yaml
services:
    Fagathe\CorePhp\Uploader\UploaderService:
        arguments:
            $security:   '@security.helper'
            $slugger:    '@slugger'
            $projectDir: '%kernel.project_dir%'
```

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

### Remplacer un fichier existant

```php
$result = $this->uploader
    ->setUploadDirectory('avatars')
    ->upload($newFile, $user->getAvatar()); // Supprime l'ancien fichier automatiquement
```

### Supprimer un fichier

```php
$this->uploader->delete($user->getAvatar());
// Ex: supprime public/uploads/avatars/alice-6673abc12.jpg
```

---

## UploaderValidationService

À utiliser **avant** l'upload pour valider le fichier.

```php
use Fagathe\CorePhp\Uploader\UploaderValidationService;

$validator = new UploaderValidationService();
// Utilise UPLOAD_SUPPORTED_MIMES et UPLOAD_MAX_FILESIZE par défaut

$result = $validator->validate($uploadedFile);

if ($result === true) {
    // Fichier valide → procéder à l'upload
} else {
    // $result est un tableau d'erreurs
    foreach ($result as $error) {
        echo $error; // "Taille limite dépassée (5 Mo max)."
                     // "Format de fichier non supporté."
    }
}
```

### Configuration personnalisée

```php
$validator = new UploaderValidationService();

$validator
    ->setAllowedMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
    ->setMaxSize(2 * 1024 * 1024); // 2 Mo

$result = $validator->validate($file);
```

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
public function upload(Request $request): Response
{
    $file = $request->files->get('document');

    if (!$file) {
        return $this->sendJson(['error' => 'Aucun fichier'], 400);
    }

    // 1. Valider
    $validator = new UploaderValidationService();
    $validation = $validator->validate($file);

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
