<?php

namespace Trustify\Bundle\MassUpdateBundle\Datagrid\MassAction;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

use Oro\Bundle\DataGridBundle\Datasource\Orm\IterableResultInterface;
use Oro\Bundle\DataGridBundle\Extension\MassAction\Actions\MassActionInterface;
use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\DataGridBundle\Datasource\Orm\OrmDatasource;
use Oro\Bundle\DataGridBundle\Datasource\ResultRecordInterface;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;

class ActionRepository
{
    const FLUSH_BATCH_SIZE = 100;

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
        $this->fieldName      = $data['mass_edit_field'];
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

                if ($iteration % self::FLUSH_BATCH_SIZE == 0) {
                    $entitiesCount += $this->finishBatch($selectedIds, $value);
                }
            }

            if ($iteration % self::FLUSH_BATCH_SIZE > 0) {
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
        $qBuilder = $this->entityManager->createQueryBuilder();

        $entitiesCount = $qBuilder->update($this->entityName, 'e')
            ->set('e.'.$this->fieldName, ':value')
            ->where($qBuilder->expr()->in('e.'.$this->identifierName, $selectedIds))
            ->getQuery()
            ->setParameter('value', $value)
            ->execute();

        $this->entityManager->flush();
        if ($this->entityManager->getConnection()->getTransactionNestingLevel() == 1) {
            $this->entityManager->clear();
        }

        $selectedIds = [];

        return $entitiesCount;
    }
}
