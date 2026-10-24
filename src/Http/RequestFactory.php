<?php

namespace Fagathe\CorePhp\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * Construit une Request à partir des variables globales PHP, sans les fichiers uploadés.
 *
 * `Request::createFromGlobals()` convertit `$_FILES` en `UploadedFile`, dont le constructeur vérifie
 * que le fichier temporaire existe. Appelée après le déplacement d'un upload (ex. UploaderService::upload),
 * elle lève une `FileNotFoundException`. Les helpers de la librairie (origine, URI, en-têtes, IP, cookies)
 * n'ont pas besoin des fichiers : on les exclut pour pouvoir créer la Request à tout moment.
 */
final class RequestFactory
{
    /**
     * @example
     * ```php
     * $request = RequestFactory::fromGlobals();
     * echo $request->getSchemeAndHttpHost(); // "https://example.com"
     * ```
     */
    public static function fromGlobals(): Request
    {
        return new Request($_GET, $_POST, [], $_COOKIE, [], $_SERVER);
    }
}
