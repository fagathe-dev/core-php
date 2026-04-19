<?php

namespace Fagathe\CorePhp\Trait;

use Fagathe\CorePhp\Enum\LoggerLevelEnum;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Trait pour la gestion centralisée des opérations de persistence (sauvegarde).
 * * Nécessite l'usage de LoggerTrait (ou la méthode generateLog) pour les logs d'erreurs.
 */
trait EntityManagerTrait
{
    use LoggerTrait;

    // La propriété DOIT être déclarée ici.
    protected readonly EntityManagerInterface $entityManager;

    /**
     * Sauvegarde une entité en base de données.
     * * Méthode générique pour persister et flusher une entité
     * avec gestion d'erreurs automatique.
     * * @param object $entity  Entité à sauvegarder
     * @param bool   $flush   Si true, exécute le flush immédiatement
     * * @return bool True si la sauvegarde a réussi, false en cas d'erreur
     */
    protected function save(object $entity, bool $flush = true): bool
    {
        try {
            $this->entityManager->persist($entity);

            if ($flush) {
                $this->entityManager->flush();
            }

            // Log de succès (nécessite generateLog, qui est dans AbstractService)
            $this->generateLog(
                LoggerLevelEnum::Info,
                [
                    'message' => 'Entité sauvegardée avec succès',
                    'entity_class' => $entity::class
                ],
                [
                    'action' => 'entity.save',
                    'entity_type' => $entity::class
                ]
            );

            return true;
        } catch (\Throwable $e) {
            // Log d'erreur (nécessite generateLog)
            $this->generateLog(
                LoggerLevelEnum::Error,
                [
                    'message' => 'Erreur lors de la sauvegarde de l\'entité',
                    'entity_class' => $entity::class,
                    'error' => $e->getMessage()
                ],
                [
                    'action' => 'entity.save.error',
                    'entity_type' => $entity::class
                ]
            );

            return false;
        }
    }

    protected function remove(object $entity, bool $flush = true): bool
    {
        try {
            $this->entityManager->remove($entity);

            if ($flush) {
                $this->entityManager->flush();
            }

            // Log de succès
            $this->generateLog(
                LoggerLevelEnum::Info,
                [
                    'message' => 'Entité supprimée avec succès',
                    'entity_class' => $entity::class
                ],
                [
                    'action' => 'entity.remove',
                    'entity_type' => $entity::class
                ]
            );

            return true;
        } catch (\Throwable $e) {
            // Log d'erreur
            $this->generateLog(
                LoggerLevelEnum::Error,
                [
                    'message' => 'Erreur lors de la suppression de l\'entité',
                    'entity_class' => $entity::class,
                    'error' => $e->getMessage()
                ],
                [
                    'action' => 'entity.remove.error',
                    'entity_type' => $entity::class
                ]
            );

            return false;
        }
    }
}