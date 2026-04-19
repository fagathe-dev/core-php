<?php
namespace Fagathe\CorePhp\Trait;

use Symfony\Component\HttpFoundation\Session\Session;

trait SessionFlashTrait
{
    /**
     * Ajoute un message flash à la session.
     * * Utilise la session pour stocker un message flash.
     * * @param string $type    Type de message (ex: 'success', 'error')
     * @param string $message Message à afficher
     */
    protected function addFlash(string $type, string $message): void
    {
        $session = new Session();
        $session->getFlashBag()->add($type, $message);
    }
}