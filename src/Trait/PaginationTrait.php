<?php

namespace Fagathe\CorePhp\Trait;

use Fagathe\CorePhp\Enum\LoggerLevelEnum;
use Doctrine\ORM\QueryBuilder;
use Knp\Component\Pager\PaginatorInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;

/**
 * Trait pour la gestion centralisée de la pagination via KnpPaginatorBundle.
 * * Nécessite l'usage de LoggerTrait (ou la méthode generateLog) pour les logs.
 */
trait PaginationTrait
{
    // La propriété DOIT être déclarée ici.
    public readonly PaginatorInterface $paginator;

    /**
     * Méthode générique de pagination avec KNP Paginator.
     * * Applique la pagination sur un QueryBuilder avec des options
     * configurables pour le tri et le filtrage.
     * * @param QueryBuilder $queryBuilder QueryBuilder à paginer
     * @param int          $page         Numéro de page (commence à 1)
     * @param int          $limit        Nombre d'éléments par page
     * @param array        $options      Options de pagination
     * - sortFieldWhitelist: array des champs autorisés pour le tri
     * - filterFieldWhitelist: array des champs autorisés pour le filtrage
     * - defaultSortFieldName: champ de tri par défaut
     * - defaultSortDirection: direction de tri par défaut (ASC|DESC)
     * - distinct: utiliser DISTINCT dans la requête
     * @param string       $action       Action à logger (optionnel)
     * @param array        $logContext   Contexte additionnel pour le log
     * * @return PaginationInterface Objet de pagination
     */
    protected function paginate(
        QueryBuilder $queryBuilder,
        int $page = 1,
        int $limit = 10,
        array $options = [],
        string $action = 'pagination.generic',
        array $logContext = []
    ): PaginationInterface {
        // Options par défaut
        $defaultOptions = [
            'defaultSortFieldName' => null,
            'defaultSortDirection' => 'DESC',
            'sortFieldWhitelist' => [],
            'filterFieldWhitelist' => [],
            'distinct' => true
        ];

        // Fusion des options
        $paginationOptions = array_merge($defaultOptions, $options);

        // Application de la pagination
        $pagination = $this->paginator->paginate(
            $queryBuilder->getQuery(),
            $page,
            $limit,
            $paginationOptions
        );

        // Log de l'action de pagination (via generateLog qui est assumé être présent)
        // Note: Assurez-vous que LoggerTrait est aussi utilisé dans la classe hôte.
        if (method_exists($this, 'generateLog')) {
            $this->generateLog(
                LoggerLevelEnum::Info,
                [
                    'message' => 'Pagination appliquée',
                    'page' => $page,
                    'limit' => $limit,
                    'total_items' => $pagination->getTotalItemCount(),
                    'options' => $paginationOptions
                ],
                array_merge([
                    'action' => $action,
                    'page' => (string) $page,
                    'limit' => (string) $limit
                ], $logContext)
            );
        }

        return $pagination;
    }
}