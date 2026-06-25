# File

Ensemble de classes pour la manipulation de fichiers : lecture/écriture, JSON, formatage de taille, détection MIME.

## Classes et traits

| Classe / Trait | Rôle |
|---|---|
| `FileHandler` | Lecture, écriture, ajout, suppression de fichiers |
| `JsonFileHandler` | Spécialisation de `FileHandler` pour le JSON |
| `FileTrait` | Trait utilitaire (formatage taille, matching MIME) |
| `FileSizeFormatter` | Formate une taille en octets vers un format lisible |
| `MimeType` | Constantes MIME et méthodes de détection par type |
| `FileTypeEnum` | Enum des types de fichiers (Archive, Image, PDF…) |

---

## FileHandler

Opérations de base sur les fichiers. Utilise `symfony/filesystem` en interne.

```php
use Fagathe\CorePhp\File\FileHandler;

$handler = new FileHandler();

// Lire un fichier
$content = $handler->read('/path/to/file.txt'); // string|null

// Écrire (crée les répertoires intermédiaires si nécessaire)
$handler->write('/path/to/file.txt', 'Hello World'); // bool

// Ajouter à la fin
$handler->append('/path/to/file.txt', "\nNouvelle ligne"); // bool

// Vérifier l'existence
$handler->exists('/path/to/file.txt'); // bool

// Supprimer
$handler->delete('/path/to/file.txt'); // bool

// Obtenir la taille en octets
$handler->getSize('/path/to/file.txt'); // int|null
```

---

## JsonFileHandler

Hérite de `FileHandler`. Ajoute l'encodage/décodage JSON.

```php
use Fagathe\CorePhp\File\JsonFileHandler;

$handler = new JsonFileHandler();

// Lire un fichier JSON → tableau PHP
$data = $handler->readJson('/path/to/data.json'); // array|null

// Écrire des données en JSON (pretty print par défaut)
$handler->writeJson('/path/to/data.json', ['key' => 'value']); // bool

// Avec des flags JSON personnalisés
$handler->writeJson('/path/to/data.json', $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Fusionner des données dans un fichier existant
$handler->appendToJson('/path/to/data.json', ['new_key' => 'new_value']); // bool
```

### Fusion avec `appendToJson`

- Si le fichier n'existe pas → crée le fichier avec les données.
- Si les deux sont des tableaux indexés → concatène les éléments.
- Si les deux sont des tableaux associatifs → fusionne récursivement.
- Autres cas → fusionne comme des objets.

---

## FileSizeFormatter

Convertit une taille en octets vers un format français lisible.

```php
use Fagathe\CorePhp\File\FileSizeFormatter;

FileSizeFormatter::formatFileSize(0);           // "0 octets"
FileSizeFormatter::formatFileSize(1024);        // "1 Ko"
FileSizeFormatter::formatFileSize(1536);        // "1,5 Ko"
FileSizeFormatter::formatFileSize(1048576);     // "1 Mo"
FileSizeFormatter::formatFileSize(1073741824);  // "1 Go"

// Précision personnalisée
FileSizeFormatter::formatFileSize(1536, 0);     // "2 Ko"
```

---

## MimeType

Constantes regroupant les types MIME par catégorie, et méthodes de détection.

```php
use Fagathe\CorePhp\File\MimeType;

// Deviner le type MIME d'un fichier
$mime = MimeType::guessMimetype('/path/to/file.pdf'); // "application/pdf"

// Vérifications par type
MimeType::isImage('/path/to/photo.jpg');    // true
MimeType::isPDF('/path/to/doc.pdf');        // true
MimeType::isArchive('/path/to/file.zip');   // true
MimeType::isAudio('/path/to/song.mp3');     // true
MimeType::isVideo('/path/to/video.mp4');    // true
MimeType::isCode('/path/to/script.php');    // true
```

### Constantes de types MIME

| Constante | Types inclus |
|---|---|
| `ARCHIVE_MIMES` | zip, tar, 7z, bzip… |
| `AUDIO_MIMES` | aac, wav, mp3, ogg… |
| `CODE_MIMES` | css, html, js, json, php, python… |
| `IMAGE_MIMES` | jpeg, png, gif, webp, svg… |
| `PDF_MIMES` | application/pdf |
| `PRESENTATION_MIMES` | ppt, pptx, odp |
| `SPREADSHEET_MIMES` | ods |
| `VIDEO_MIMES` | mp4, avi, webm, ogg… |

---

## FileTypeEnum

Enum PHP 8.1 qui associe un type MIME à un type de fichier lisible.

```php
use Fagathe\CorePhp\File\FileTypeEnum;

// Identifier le type depuis un type MIME
$type = FileTypeEnum::matchMime('image/jpeg'); // "Image"
$type = FileTypeEnum::matchMime('application/pdf'); // "PDF"
$type = FileTypeEnum::matchMime('unknown/type'); // null

// Récupérer toutes les valeurs lisibles
$values = FileTypeEnum::getSupportedValues();
// ['Archive', 'Audio', 'Code', 'Image', 'PDF', 'Présentation', 'Tableur', 'Traitement de texte', 'Texte', 'Vidéo']
```

---

## FileTrait

Trait à inclure dans une classe pour exposer des helpers de manipulation de fichiers.

```php
use Fagathe\CorePhp\File\FileTrait;
use Symfony\Component\Filesystem\Filesystem;

class MyService
{
    use FileTrait;

    public function __construct()
    {
        $this->setFilesystem(new Filesystem());
    }

    public function process(string $mimeType, int $size): void
    {
        $type = $this->matchMimeType($mimeType);  // "Image", "PDF", null…
        $formatted = $this->formatFileSize($size); // "5,25 Mo"
    }
}
```
