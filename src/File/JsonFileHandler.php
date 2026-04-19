<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\File;

/**
 * Gestionnaire spécialisé pour les fichiers JSON.
 * 
 * Étend FileHandler pour fournir des méthodes spécifiques
 * à l'encodage et au décodage de fichiers JSON avec
 * gestion d'erreurs et validation.
 * 
 * @author Journal App
 */
class JsonFileHandler extends FileHandler
{
    /**
     * Lit et décode un fichier JSON.
     * 
     * @param string $filePath Chemin vers le fichier JSON
     * @param bool $associative Si true, retourne un array, sinon un objet
     * @return array|object|null Données décodées ou null si erreur
     */
    public function readJson(string $filePath, bool $associative = true): array|object|null
    {
        $content = $this->read($filePath);

        if ($content === null) {
            return null;
        }

        return $this->decodeJson($content, $associative);
    }

    /**
     * Encode et écrit des données dans un fichier JSON.
     * 
     * @param string $filePath Chemin vers le fichier JSON
     * @param mixed $data Données à encoder
     * @param int $flags Flags JSON (par défaut: JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
     * @return bool True si l'écriture a réussi
     */
    public function writeJson(string $filePath, mixed $data, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE): bool
    {
        $jsonContent = $this->encodeJson($data, $flags);

        if ($jsonContent === null) {
            return false;
        }

        return $this->write($filePath, $jsonContent);
    }

    /**
     * Ajoute des données à un fichier JSON existant.
     * 
     * Pour les arrays, ajoute les éléments.
     * Pour les objets, fusionne les propriétés.
     * 
     * @param string $filePath Chemin vers le fichier JSON
     * @param mixed $data Données à ajouter
     * @param int $flags Flags JSON
     * @return bool True si l'ajout a réussi
     */
    public function appendToJson(string $filePath, mixed $data, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE): bool
    {
        $existingData = $this->readJson($filePath, true);

        // Si le fichier n'existe pas ou est vide, créer avec les nouvelles données
        if ($existingData === null) {
            return $this->writeJson($filePath, $data, $flags);
        }

        // Fusionner les données selon le type
        if (is_array($existingData) && is_array($data)) {
            // Fusionner récursivement les arrays associatifs
            $mergedData = $this->mergeArraysRecursively($existingData, $data);
        } else {
            // Pour les autres cas, traiter comme des objets
            $mergedData = (object) array_merge((array) $existingData, (array) $data);
        }

        return $this->writeJson($filePath, $mergedData, $flags);
    }

    /**
     * Fusionne récursivement deux arrays.
     * 
     * @param array $existing Array existant
     * @param array $new Nouvelles données
     * @return array Array fusionné
     */
    private function mergeArraysRecursively(array $existing, array $new): array
    {
        foreach ($new as $key => $value) {
            if (isset($existing[$key])) {
                if (is_array($existing[$key]) && is_array($value)) {
                    // Si les deux sont des arrays, on les fusionne
                    if (array_is_list($existing[$key]) && array_is_list($value)) {
                        // Arrays indexés : on ajoute les éléments
                        $existing[$key] = array_merge($existing[$key], $value);
                    } else {
                        // Arrays associatifs : fusion récursive
                        $existing[$key] = $this->mergeArraysRecursively($existing[$key], $value);
                    }
                } else {
                    // Remplacer la valeur existante
                    $existing[$key] = $value;
                }
            } else {
                // Ajouter la nouvelle clé
                $existing[$key] = $value;
            }
        }

        return $existing;
    }

    /**
     * Encode des données en JSON.
     * 
     * @param mixed $data Données à encoder
     * @param int $flags Flags JSON
     * @return string|null JSON encodé ou null si erreur
     */
    public function encodeJson(mixed $data, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE): ?string
    {
        $json = json_encode($data, $flags);

        if ($json === false) {
            $error = json_last_error_msg();
            error_log("Erreur encodage JSON: $error");
            return null;
        }

        return $json;
    }

    /**
     * Décode une chaîne JSON.
     * 
     * @param string $json Chaîne JSON à décoder
     * @param bool $associative Si true, retourne un array, sinon un objet
     * @return array|object|null Données décodées ou null si erreur
     */
    public function decodeJson(string $json, bool $associative = true): array|object|null
    {
        if (empty(trim($json))) {
            return $associative ? [] : (object) [];
        }

        $data = json_decode($json, $associative);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = json_last_error_msg();
            error_log("Erreur décodage JSON: $error");
            return null;
        }

        return $data;
    }

    /**
     * Valide si une chaîne est un JSON valide.
     * 
     * @param string $json Chaîne à valider
     * @return bool True si JSON valide
     */
    public function isValidJson(string $json): bool
    {
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Valide si un fichier contient du JSON valide.
     * 
     * @param string $filePath Chemin vers le fichier
     * @return bool True si le fichier contient du JSON valide
     */
    public function isValidJsonFile(string $filePath): bool
    {
        $content = $this->read($filePath);

        if ($content === null) {
            return false;
        }

        return $this->isValidJson($content);
    }

    /**
     * Obtient des statistiques sur un fichier JSON.
     * 
     * @param string $filePath Chemin vers le fichier JSON
     * @return array|null Statistiques ou null si erreur
     */
    public function getJsonStats(string $filePath): ?array
    {
        if (!$this->exists($filePath)) {
            return null;
        }

        $data = $this->readJson($filePath, true);
        $size = $this->getSize($filePath);
        $lastModified = $this->getLastModified($filePath);

        if ($data === null) {
            return null;
        }

        $stats = [
            'file_size' => $size,
            'file_size_formatted' => $this->getFormattedSize($filePath),
            'last_modified' => $lastModified?->format('Y-m-d H:i:s'),
            'is_valid_json' => true,
            'data_type' => gettype($data),
        ];

        if (is_array($data)) {
            $stats['element_count'] = count($data);
            $stats['is_empty'] = empty($data);
        } elseif (is_object($data)) {
            $stats['property_count'] = count((array) $data);
            $stats['is_empty'] = empty((array) $data);
        }

        return $stats;
    }

    /**
     * Formate joliment un fichier JSON (pretty print).
     * 
     * @param string $filePath Chemin vers le fichier JSON
     * @return bool True si le formatage a réussi
     */
    public function formatJsonFile(string $filePath): bool
    {
        $data = $this->readJson($filePath);

        if ($data === null) {
            return false;
        }

        return $this->writeJson($filePath, $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Compacte un fichier JSON (supprime les espaces).
     * 
     * @param string $filePath Chemin vers le fichier JSON
     * @return bool True si la compaction a réussi
     */
    public function compactJsonFile(string $filePath): bool
    {
        $data = $this->readJson($filePath);

        if ($data === null) {
            return false;
        }

        return $this->writeJson($filePath, $data, JSON_UNESCAPED_UNICODE);
    }
}