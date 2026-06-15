<?php

/**
 * Sélectionne un élément aléatoire avec ou sans poids.
 * @param array $values Les options possibles [true, false] ou ['A', 'B', 'C']
 * @param array|null $weights Les poids correspondants [10, 90] ou null pour uniforme
 */
function random_choices(array $values, ?array $weights = null): mixed
{
    // Si aucun poids n'est spécifié, sélection aléatoire uniforme
    if ($weights === null) {
        return $values[array_rand($values)];
    }

    $total_weight = array_sum($weights);

    // Protection si la somme des poids est 0
    if ($total_weight === 0) {
        return $values[array_rand($values)];
    }

    $random = mt_rand(1, $total_weight);
    $current_weight = 0;

    foreach ($weights as $index => $weight) {
        $current_weight += $weight;
        if ($random <= $current_weight) {
            return $values[$index];
        }
    }

    return $values[array_key_last($values)];
}

/**
 * @description Sélectionne aléatoirement un ou plusieurs éléments d'un tableau.
 * Retourne un élément unique si la longueur demandée est de 1, ou un sous-tableau 
 * d'éléments mélangés si la longueur est supérieure. Retourne false si le tableau est vide.
 * * @param array|null $values Le tableau source dans lequel piocher les valeurs.
 * @param int $length Le nombre d'éléments à retourner (1 par défaut).
 * * @return mixed L'élément tiré au sort, un tableau d'éléments, ou false en cas d'erreur.
 */
function random_picker(?array $values = null, int $length = 1): mixed
{
    if (!is_array($values) || empty($values)) {
        return false;
    }

    $max_index = count($values) - 1;
    shuffle($values);

    if ($length === 1) {
        return $values[mt_rand(0, $max_index)];
    }

    return array_slice($values, 0, $length);
}