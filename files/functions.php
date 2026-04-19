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