<?php

namespace Trustify\Bundle\MassUpdateBundle\Datagrid\MassAction;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\QueryBuilder;

use Oro\Bundle\DataGridBundle\Datasource\Orm\IterableResultInterface;
use Oro\Bundle\DataGridBundle\Extension\MassAction\Actions\MassActionInterface;
use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\DataGridBundle\Datasource\Orm\OrmDatasource;
use Oro\Bundle\DataGridBundle\Datasource\ResultRecordInterface;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Component\PropertyAccess\PropertyAccessor;

class ActionRepository
{
    /** @var int */
    protected $batchSize = 100;

    /** @var DoctrineHelper */
    protected $doctrineHelper;

    /** @var EntityManagerInterface */
    protected $entityManager;

    /** @var string */
    protected $entityName;

    /** @var string */
    protected $fieldName;

    /** @var string */
    protected $identifierName;

    /**
     * @param DoctrineHelper $doctrineHelper
     */
    public function __construct(DoctrineHelper $doctrineHelper)
    {
        $this->doctrineHelper = $doctrineHelper;
    }

    /**
     * @param int $size
     *
     * @return ActionRepository
     */
    public function setBatchSize($size)
    {
        $this->batchSize = (int)$size;

        return $this;
    }

    /**
     * @param DatagridInterface $datagrid
     *
     * @return string|null
     */
    public function getEntityName(DatagridInterface $datagrid)
    {
        /** @var OrmDatasource $dataSource */
        $dataSource = $datagrid->getDatasource();
        if (!$dataSource) {
            return null;
        }

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $dataSource->getQueryBuilder();
        $rootEntities = $queryBuilder->getRootEntities();

        return reset($rootEntities);
    }

    /**
     * {@inheritdoc}
     */
    public function batchUpdate(MassActionInterface $massAction, IterableResultInterface $results, array $data)
    {
        $this->entityName     = $massAction->getOptions()->offsetGet('entityName');
        $this->fieldName      = empty($data['mass_edit_field']) ? null : $data['mass_edit_field'];
        if (empty($this->fieldName)) {
            throw new \RuntimeException("Field name was not specified with option 'mass_edit_field'");
        }

        $this->identifierName = $this->doctrineHelper->getSingleEntityIdentifierFieldName($this->entityName);
        $value                = $data[$this->fieldName];

        $selectedIds   = [];
        $entitiesCount = 0;
        $iteration     = 0;

        $this->entityManager = $this->doctrineHelper->getEntityManager($this->entityName);
        $this->entityManager->beginTransaction();

        try {
            set_time_limit(0);

            foreach ($results as $result) {
                /** @var $result ResultRecordInterface */
                $selectedIds[] = $result->getValue($this->identifierName);

                $iteration++;

                if ($iteration % $this->batchSize == 0) {
                    $entitiesCount += $this->finishBatch($selectedIds, $value);
                }
            }

            if ($iteration % $this->batchSize > 0) {
                $entitiesCount += $this->finishBatch($selectedIds, $value);
            }

            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        return $entitiesCount;
    }

    /**
     * @param array  $selectedIds
     * @param string $value
     *
     * @return int
     */
    protected function finishBatch(array &$selectedIds, $value)
    {
        // first initiate preUpdate event for batch
        $qBuilder = $this->entityManager->createQueryBuilder();
        $entities = $qBuilder->from($this->entityName, 'e')
            ->select('e')
            ->where($qBuilder->expr()->in('e.' . $this->identifierName, $selectedIds))
            ->getQuery()
            ->getResult();

        $eventManager = $this->entityManager->getEventManager();
        $propAccessor = new PropertyAccessor();


        $metadata = $this->doctrineHelper->getEntityMetadata($this->entityName);
        if ($metadata->hasAssociation($this->fieldName)) {
            $relClass = $metadata->getAssociationTargetClass($this->fieldName);
            $value = $this->entityManager->getReference($relClass, $value);
        }

        foreach ($entities as $entity) {
            $old = $propAccessor->getValue($entity, $this->fieldName);
            $changeSet = [$this->fieldName => [$old, $value]];

            $this->entityManager->getUnitOfWork()->propertyChanged($entity, $this->fieldName, $old, $value);
            //$propAccessor->setValue($entity, $this->fieldName, $value);

            $eventManager->dispatchEvent(
                Events::preUpdate,
                new PreUpdateEventArgs($entity, $this->entityManager, $changeSet)
            );
        }

        // perform mass update
        $qBuilder = $this->entityManager->createQueryBuilder();
        $entitiesCount = $qBuilder->update($this->entityName, 'e')
            ->set('e.'.$this->fieldName, ':value')
            ->where($qBuilder->expr()->in('e.' . $this->identifierName, $selectedIds))
            ->getQuery()
            ->setParameter('value', $value)
            ->execute();

        foreach ($entities as $entity) {
            $eventManager->dispatchEvent(
                Events::postUpdate,
                new LifecycleEventArgs($entity, $this->entityManager)
            );
        }

        // TODO: ensure Processes and DataAudit listeners are able to 'see' mass-update changes
        // right now it doesn't work
        $this->entityManager->flush();
        if ($this->entityManager->getConnection()->getTransactionNestingLevel() == 1) {
            $this->entityManager->clear();
        }

        $selectedIds = [];

        return $entitiesCount;
    }
}
