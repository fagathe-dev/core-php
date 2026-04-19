<?php

namespace Fagathe\CorePhp\Generator;

class TokenGenerator
{
    public const ALPHABET_NUMERIC = '0123456789';
    public const ALPHABET_ALPHANUMERIC = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * Génère un token aléatoire sécurisé
     * * @param int $length La longueur souhaitée du token
     * @param string $alphabet L'alphabet à utiliser (par défaut ALPHANUMERIC)
     * @return string
     */
    public function generate(int $length = 32, string $alphabet = self::ALPHABET_ALPHANUMERIC): string
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('La longueur du token doit être supérieure à 0');
        }

        $token = '';
        $max = strlen($alphabet) - 1;

        for ($i = 0; $i < $length; $i++) {
            // random_int est cryptographiquement sécurisé (contrairement à rand ou mt_rand)
            // Cela garantit que le token est impossible à prédire
            $token .= $alphabet[random_int(0, $max)];
        }

        return $token;
    }

    /**
     * Helper pour générer un code uniquement numérique (ex: code 2FA, PIN)
     */
    public function generateNumeric(int $length = 6): string
    {
        return $this->generate($length, self::ALPHABET_NUMERIC);
    }
}