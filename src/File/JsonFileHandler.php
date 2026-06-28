<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\File;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use InvalidArgumentException;
use RuntimeException;

/**
 * Gestionnaire spécialisé pour les fichiers JSON.
 *
 * Étend FileHandler pour fournir des méthodes spécifiques
 * à l'encodage et au décodage de fichiers JSON avec
 * gestion d'erreurs, validation et hydratation de DTOs
 * via réflexion (sans dépendance externe).
 *
 * Hydratation supportée :
 *   - Scalaires (string, int, float, bool)
 *   - Nullable (?string, ?int, …)
 *   - Tableaux de scalaires (array sans annotation → array brut)
 *   - Tableaux de DTOs via annotation @var ClassName[] sur le paramètre
 *   - DTOs imbriqués (paramètre dont le type est une classe)
 *
 * @author Journal App
 */
class JsonFileHandler extends FileHandler
{
    // -------------------------------------------------------------------------
    // Lecture / écriture JSON brut (API d'origine inchangée)
    // -------------------------------------------------------------------------

    /**
     * Lit et décode un fichier JSON (avec support optionnel d'extraction de nœud).
     *
     * @param string      $filePath    Chemin vers le fichier JSON
     * @param bool        $associative Si true, retourne un array, sinon un objet
     * @param string|null $rootKey     Clé (ou chemin pointé) vers le nœud à extraire. Null = racine.
     *
     * @return array|object|null Données décodées ou null si erreur
     */
    public function readJson(string $filePath, bool $associative = true, ?string $rootKey = null): array|object|null
    {
        // Si une clé est demandée, on force d'abord le décodage en tableau pour extractNode
        $decodeAsArray = $rootKey !== null ? true : $associative;

        $content = $this->read($filePath);

        if ($content === null) {
            return null;
        }

        $data = $this->decodeJson($content, $decodeAsArray);

        if ($data === null) {
            return null;
        }

        // Si une clé de ciblage est fournie, on extrait le sous-nœud
        if ($rootKey !== null) {
            $data = $this->extractNode($data, $rootKey, $filePath);

            if ($data === null) {
                return null;
            }

            // Si l'utilisateur a demandé explicitement un objet stdClass ($associative = false),
            // on reconvertit proprement le tableau extrait en objet (gestion de la profondeur incluse)
            if (!$associative) {
                return json_decode(json_encode($data), false);
            }
        }

        return $data;
    }

    /**
     * Encode et écrit des données dans un fichier JSON.
     *
     * @param string $filePath Chemin vers le fichier JSON
     * @param mixed  $data     Données à encoder
     * @param int    $flags    Flags JSON (par défaut : JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
     *
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
     * @param mixed  $data     Données à ajouter
     * @param int    $flags    Flags JSON
     *
     * @return bool True si l'ajout a réussi
     */
    public function appendToJson(string $filePath, mixed $data, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE): bool
    {
        $existingData = $this->readJson($filePath, true);

        if ($existingData === null) {
            return $this->writeJson($filePath, $data, $flags);
        }

        if (is_array($existingData) && is_array($data)) {
            $mergedData = $this->mergeArraysRecursively($existingData, $data);
        } else {
            $mergedData = (object) array_merge((array) $existingData, (array) $data);
        }

        return $this->writeJson($filePath, $mergedData, $flags);
    }

    // -------------------------------------------------------------------------
    // Hydratation DTO (nouvelles méthodes)
    // -------------------------------------------------------------------------

    /**
     * Lit un fichier JSON et l'hydrate dans un DTO typé.
     *
     * Le paramètre $rootKey permet de cibler un nœud précis du JSON
     * avant l'hydratation. Il supporte la notation pointée pour
     * descendre dans des structures imbriquées.
     *
     * Exemples :
     *   // Racine directe
     *   $dto = $handler->readJsonAs('/data/profile.json', ProfileDTO::class);
     *
     *   // Clé simple  →  { "data": { "name": "...", ... } }
     *   $dto = $handler->readJsonAs('/data/profile.json', ProfileDTO::class, 'data');
     *
     *   // Notation pointée  →  { "api": { "v1": { "name": "...", ... } } }
     *   $dto = $handler->readJsonAs('/data/config.json', ConfigDTO::class, 'api.v1');
     *
     * @template T of object
     *
     * @param string          $filePath Chemin vers le fichier JSON
     * @param class-string<T> $dtoClass Classe cible (DTO avec constructeur typé)
     * @param string|null     $rootKey  Clé (ou chemin pointé) vers le nœud à hydrater.
     *                                  Null = racine du JSON.
     *
     * @return T|null Instance hydratée ou null si le fichier est illisible / invalide
     *                ou si la clé demandée est introuvable
     */
    public function readJsonAs(string $filePath, string $dtoClass, ?string $rootKey = null): ?object
    {
        // On délègue l'extraction du nœud directement à la nouvelle logique de readJson
        $data = $this->readJson($filePath, true, $rootKey);

        if ($data === null) {
            return null;
        }

        try {
            return $this->hydrate($dtoClass, $data);
        } catch (InvalidArgumentException | RuntimeException $e) {
            error_log("Erreur hydratation DTO [{$dtoClass}] depuis {$filePath} : {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Extrait un nœud depuis un tableau associatif via une clé simple ou un chemin pointé.
     *
     * @param array  $data     Données JSON complètes
     * @param string $rootKey  Clé simple ("data") ou chemin pointé ("api.v1.config")
     * @param string $filePath Utilisé uniquement pour le message d'erreur
     *
     * @return array|null Le nœud ciblé, ou null s'il est introuvable / pas un tableau
     */
    private function extractNode(array $data, string $rootKey, string $filePath): ?array
    {
        $node = $data;

        foreach (explode('.', $rootKey) as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                error_log("Clé JSON introuvable : \"{$rootKey}\" (segment \"{$segment}\") dans {$filePath}.");
                return null;
            }

            $node = $node[$segment];
        }

        if (!is_array($node)) {
            error_log("Le nœud \"{$rootKey}\" dans {$filePath} n'est pas un objet JSON hydratable.");
            return null;
        }

        return $node;
    }

    /**
     * Lit un fichier JSON dont la racine est un tableau et hydrate chaque élément.
     *
     * Exemple :
     *   $items = $handler->readJsonArrayAs('/data/projects.json', 'projects', ProjectDTO::class);
     *
     * @template T of object
     *
     * @param string          $filePath  Chemin vers le fichier JSON
     * @param string|null     $rootKey   Clé racine du tableau dans le JSON (null = racine directe)
     * @param class-string<T> $itemClass Classe cible pour chaque élément
     *
     * @return T[] Tableau d'instances hydratées (vide si erreur)
     */
    public function readJsonArrayAs(string $filePath, ?string $rootKey, string $itemClass): array
    {
        $data = $this->readJson($filePath, true, $rootKey);

        if (!is_array($data)) {
            return [];
        }

        $items = $data;

        if (!is_array($items)) {
            return [];
        }

        $result = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            try {
                $result[] = $this->hydrate($itemClass, $item);
            } catch (InvalidArgumentException | RuntimeException $e) {
                error_log("Erreur hydratation DTO [{$itemClass}] : {$e->getMessage()}");
            }
        }

        return $result;
    }

    /**
     * Hydrate un DTO depuis un tableau associatif.
     *
     * Résolution des types :
     *   - Scalaires natifs (string, int, float, bool) : cast strict
     *   - Nullable : null autorisé si la valeur est absente et le paramètre est nullable
     *   - Valeur par défaut : utilisée si la clé est absente du tableau
     *   - DTO imbriqué : appel récursif si le type est une classe
     *   - Tableau de DTOs : détecté via @var NomClasse[] dans le docblock du paramètre
     *
     * @template T of object
     *
     * @param class-string<T> $dtoClass  Classe cible
     * @param array           $data      Données brutes (tableau associatif)
     *
     * @return T Instance hydratée
     *
     * @throws InvalidArgumentException Si un paramètre requis est absent ou d'un type non supporté
     * @throws RuntimeException         Si la classe n'est pas instanciable
     */
    public function hydrate(string $dtoClass, array $data): object
    {
        $ref = new ReflectionClass($dtoClass);

        if (!$ref->isInstantiable()) {
            throw new RuntimeException("La classe {$dtoClass} n'est pas instanciable.");
        }

        $constructor = $ref->getConstructor();

        // Pas de constructeur → instanciation directe
        if ($constructor === null) {
            return $ref->newInstance();
        }

        $args = [];

        foreach ($constructor->getParameters() as $param) {
            $args[] = $this->resolveParameter($param, $data, $dtoClass);
        }

        return $ref->newInstanceArgs($args);
    }

    // -------------------------------------------------------------------------
    // Résolution interne d'un paramètre
    // -------------------------------------------------------------------------

    /**
     * Résout la valeur d'un paramètre de constructeur depuis le tableau de données.
     *
     * @throws InvalidArgumentException
     */
    private function resolveParameter(ReflectionParameter $param, array $data, string $dtoClass): mixed
    {
        $name = $param->getName();
        $key = $this->toSnakeCase($name);  // camelCase → snake_case pour matcher le JSON
        $value = $data[$key] ?? $data[$name] ?? null; // fallback camelCase si le JSON utilise camelCase

        $hasValue = array_key_exists($key, $data) || array_key_exists($name, $data);

        // Valeur absente : default ou nullable, sinon erreur
        if (!$hasValue) {
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }

            if ($param->allowsNull()) {
                return null;
            }

            throw new InvalidArgumentException(
                "Paramètre requis \${$name} absent dans les données pour {$dtoClass}."
            );
        }

        $type = $param->getType();

        // Pas de type déclaré → valeur brute
        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        $typeName = $type->getName();

        // Valeur null explicite
        if ($value === null) {
            if ($type->allowsNull()) {
                return null;
            }
            throw new InvalidArgumentException(
                "Paramètre \${$name} non nullable reçoit null dans {$dtoClass}."
            );
        }

        // Scalaires natifs
        if (in_array($typeName, ['string', 'int', 'float', 'bool'], true)) {
            return $this->castScalar($value, $typeName, $name, $dtoClass);
        }

        // Tableau : on cherche d'abord @var ClassName[] dans le docblock
        if ($typeName === 'array') {
            return $this->resolveArray($param, $value, $dtoClass);
        }

        // DTO imbriqué : le type est une classe
        if (class_exists($typeName) && is_array($value)) {
            return $this->hydrate($typeName, $value);
        }

        // Fallback : valeur brute
        return $value;
    }

    /**
     * Résout un paramètre de type array.
     *
     * Si le docblock contient @var ClassName[], hydrate chaque élément.
     * Sinon retourne le tableau brut.
     *
     * @throws InvalidArgumentException
     */
    private function resolveArray(ReflectionParameter $param, mixed $value, string $dtoClass): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException(
                "Paramètre \${$param->getName()} attend un tableau dans {$dtoClass}."
            );
        }

        $itemClass = $this->extractArrayItemClass($param);

        if ($itemClass === null || !class_exists($itemClass)) {
            return $value; // tableau brut
        }

        $result = [];

        foreach ($value as $item) {
            if (!is_array($item)) {
                $result[] = $item;
                continue;
            }

            $result[] = $this->hydrate($itemClass, $item);
        }

        return $result;
    }

    /**
     * Extrait le nom de classe depuis @var ClassName[] dans le docblock d'un paramètre.
     *
     * Supporte les formats :
     *   @var App\DTO\FooDTO[]
     *   @var FooDTO[]
     */
    private function extractArrayItemClass(ReflectionParameter $param): ?string
    {
        // PHP 8 : on tente d'abord via le docblock du constructeur
        $constructor = $param->getDeclaringFunction();
        $doc = $constructor->getDocComment();

        if ($doc === false) {
            return null;
        }

        $paramName = $param->getName();

        // Cherche : @param ClassName[] $paramName  ou  @var ClassName[]
        $pattern = '/@param\s+([\w\\\\]+)\[\]\s+\$' . preg_quote($paramName, '/') . '/';

        if (preg_match($pattern, $doc, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Cast strict d'une valeur vers un type scalaire PHP.
     *
     * @throws InvalidArgumentException
     */
    private function castScalar(mixed $value, string $type, string $paramName, string $dtoClass): string|int|float|bool
    {
        return match ($type) {
            'string' => (string) $value,
            'int' => filter_var($value, FILTER_VALIDATE_INT) !== false
            ? (int) $value
            : throw new InvalidArgumentException(
                "Paramètre \${$paramName} attend un int dans {$dtoClass}, reçu : " . gettype($value)
            ),
            'float' => is_numeric($value)
            ? (float) $value
            : throw new InvalidArgumentException(
                "Paramètre \${$paramName} attend un float dans {$dtoClass}, reçu : " . gettype($value)
            ),
            'bool' => (bool) $value,
            default => throw new InvalidArgumentException(
                "Type scalaire non supporté : {$type} pour \${$paramName} dans {$dtoClass}."
            ),
        };
    }

    /**
     * Convertit un nom camelCase en snake_case.
     *
     * Exemples :
     *   longDescription → long_description
     *   startDate       → start_date
     *   cv              → cv
     */
    private function toSnakeCase(string $name): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($name)));
    }

    // -------------------------------------------------------------------------
    // Encodage / décodage (API d'origine inchangée)
    // -------------------------------------------------------------------------

    /**
     * Encode des données en JSON.
     *
     * @param mixed $data  Données à encoder
     * @param int   $flags Flags JSON
     *
     * @return string|null JSON encodé ou null si erreur
     */
    public function encodeJson(mixed $data, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE): ?string
    {
        $json = json_encode($data, $flags);

        if ($json === false) {
            error_log('Erreur encodage JSON : ' . json_last_error_msg());
            return null;
        }

        return $json;
    }

    /**
     * Décode une chaîne JSON.
     *
     * @param string $json        Chaîne JSON à décoder
     * @param bool   $associative Si true, retourne un array, sinon un objet
     *
     * @return array|object|null Données décodées ou null si erreur
     */
    public function decodeJson(string $json, bool $associative = true): array|object|null
    {
        if (empty(trim($json))) {
            return $associative ? [] : (object) [];
        }

        $data = json_decode($json, $associative);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Erreur décodage JSON : ' . json_last_error_msg());
            return null;
        }

        return $data;
    }

    /**
     * Valide si une chaîne est un JSON valide.
     */
    public function isValidJson(string $json): bool
    {
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Valide si un fichier contient du JSON valide.
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
     */
    public function compactJsonFile(string $filePath): bool
    {
        $data = $this->readJson($filePath);

        if ($data === null) {
            return false;
        }

        return $this->writeJson($filePath, $data, JSON_UNESCAPED_UNICODE);
    }

    // -------------------------------------------------------------------------
    // Fusion récursive (API d'origine inchangée)
    // -------------------------------------------------------------------------

    private function mergeArraysRecursively(array $existing, array $new): array
    {
        foreach ($new as $key => $value) {
            if (isset($existing[$key])) {
                if (is_array($existing[$key]) && is_array($value)) {
                    if (array_is_list($existing[$key]) && array_is_list($value)) {
                        $existing[$key] = array_merge($existing[$key], $value);
                    } else {
                        $existing[$key] = $this->mergeArraysRecursively($existing[$key], $value);
                    }
                } else {
                    $existing[$key] = $value;
                }
            } else {
                $existing[$key] = $value;
            }
        }

        return $existing;
    }
}
