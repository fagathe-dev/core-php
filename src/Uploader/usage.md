# Uploader — Documentation d'utilisation

## Namespace `Fagathe\CorePhp\Uploader`

| Classe | Rôle |
|--------|------|
| `UploaderService` | Service principal d'upload : déplace le fichier, génère un nom unique, supprime l'ancien fichier si nécessaire. |
| `UploaderValidationService` | Valide le fichier (taille max, MIME types autorisés) avant l'upload. |
| `UploadResult` | DTO retourné après un upload réussi (chemin relatif, nom original, nouveau nom, taille, MIME type, extension). |
| `FileUploadException` | Exception dédiée aux erreurs d'upload. |

---

## Configuration requise

Définir les constantes suivantes (ex. dans un fichier `constants.php`) :

```php
define('PUBLIC_DIR', 'public');
define('UPLOAD_DIR', 'uploads');
define('UPLOAD_SUPPORTED_MIMES', ['image/jpeg', 'image/png', 'image/webp', 'application/pdf']);
define('UPLOAD_MAX_FILESIZE', 5 * 1024 * 1024); // 5 Mo
```

---

## Exemple de service : `App\Service\UserService`

### 1. Upload via requête AJAX (JSON / FormData)

Le contrôleur reçoit un `UploadedFile` depuis la `Request` Symfony :

**Contrôleur** (`App\Controller\UserController`)

```php
<?php

namespace App\Controller;

use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    #[Route('/api/user/{id}/avatar', name: 'api_user_avatar', methods: ['POST'])]
    public function uploadAvatar(int $id, Request $request, UserService $userService): JsonResponse
    {
        $file = $request->files->get('avatar');

        if (!$file) {
            return $this->json(['error' => 'Aucun fichier reçu.'], 400);
        }

        try {
            $result = $userService->updateAvatar($id, $file);

            return $this->json([
                'success' => true,
                'path' => $result->relativePath,
                'originalName' => $result->originalName,
            ]);
        } catch (\Fagathe\CorePhp\Uploader\FileUploadException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }
}
```

**Service** (`App\Service\UserService`)

```php
<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Fagathe\CorePhp\Uploader\FileUploadException;
use Fagathe\CorePhp\Uploader\UploaderService;
use Fagathe\CorePhp\Uploader\UploaderValidationService;
use Fagathe\CorePhp\Uploader\UploadResult;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UserService
{
    public function __construct(
        private readonly UploaderService $uploaderService,
        private readonly UploaderValidationService $validationService,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Met à jour l'avatar d'un utilisateur (usage AJAX).
     */
    public function updateAvatar(int $userId, UploadedFile $file): UploadResult
    {
        // 1. Validation du fichier
        $this->validationService
            ->setAllowedMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->setMaxSize(2 * 1024 * 1024); // 2 Mo max pour un avatar

        $validation = $this->validationService->validate($file);

        if ($validation !== true) {
            throw new FileUploadException(implode(' ', $validation));
        }

        // 2. Récupération de l'utilisateur
        $user = $this->userRepository->find($userId);

        if (!$user) {
            throw new \RuntimeException('Utilisateur introuvable.');
        }

        // 3. Upload du fichier (suppression automatique de l'ancien)
        $result = $this->uploaderService
            ->setUploadDirectory('avatars')
            ->upload($file, $user->getAvatar());

        // 4. Mise à jour de l'entité
        $user->setAvatar($result->relativePath);
        $this->em->flush();

        return $result;
    }
}
```

**Côté front (JavaScript)**

```javascript
const input = document.querySelector('#avatar-input');
const formData = new FormData();
formData.append('avatar', input.files[0]);

fetch('/api/user/42/avatar', {
    method: 'POST',
    body: formData,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
    },
})
    .then(res => res.json())
    .then(data => console.log(data));
```

---

### 2. Upload via un formulaire Symfony (FormType)

**FormType** (`App\Form\UserType`)

```php
<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('avatar', FileType::class, [
                'label' => 'Photo de profil',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPEG, PNG ou WebP).',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
```

**Contrôleur** (`App\Controller\UserController`)

```php
<?php

namespace App\Controller;

use App\Form\UserType;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    #[Route('/user/{id}/edit', name: 'user_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, UserService $userService): Response
    {
        $user = $userService->getUser($id);
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $avatarFile = $form->get('avatar')->getData();

            if ($avatarFile) {
                $userService->updateAvatar($user->getId(), $avatarFile);
            }

            $this->addFlash('success', 'Profil mis à jour.');

            return $this->redirectToRoute('user_edit', ['id' => $user->getId()]);
        }

        return $this->render('user/edit.html.twig', [
            'form' => $form,
        ]);
    }
}
```

**Service** (`App\Service\UserService`) — même méthode `updateAvatar()` que pour l'AJAX :

```php
public function updateAvatar(int $userId, UploadedFile $file): UploadResult
{
    // Validation
    $this->validationService
        ->setAllowedMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
        ->setMaxSize(2 * 1024 * 1024);

    $validation = $this->validationService->validate($file);

    if ($validation !== true) {
        throw new FileUploadException(implode(' ', $validation));
    }

    // Upload
    $user = $this->userRepository->find($userId);
    $result = $this->uploaderService
        ->setUploadDirectory('avatars')
        ->upload($file, $user->getAvatar());

    // Persistance
    $user->setAvatar($result->relativePath);
    $this->em->flush();

    return $result;
}
```

---

## Propriétés de `UploadResult`

| Propriété | Type | Description |
|-----------|------|-------------|
| `relativePath` | `string` | Chemin relatif depuis `public/` (ex: `uploads/avatars/photo-6848a3f1e2b4c.jpg`) |
| `originalName` | `string` | Nom original du fichier envoyé par l'utilisateur |
| `newName` | `string` | Nom unique généré côté serveur |
| `size` | `int` | Taille du fichier en octets |
| `mimeType` | `string` | Type MIME du fichier |
| `extension` | `string` | Extension du fichier |

---

## Gestion des erreurs

```php
use Fagathe\CorePhp\Uploader\FileUploadException;

try {
    $result = $this->uploaderService->upload($file);
} catch (FileUploadException $e) {
    // Erreur liée à l'upload (droits, déplacement, etc.)
    // $e->getMessage() contient le détail
}
```

---

## Suppression d'un fichier

```php
$this->uploaderService->delete('uploads/avatars/ancien-fichier.jpg');
```
