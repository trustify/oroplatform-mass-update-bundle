<?php

namespace Trustify\Bundle\MassUpdateBundle\Datagrid\MassAction;

use Symfony\Component\Translation\TranslatorInterface;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

use Oro\Bundle\SecurityBundle\SecurityFacade;
use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\DataGridBundle\Datasource\Orm\OrmDatasource;
use Oro\Bundle\DataGridBundle\Datasource\ResultRecordInterface;
use Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionHandlerArgs;
use Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionHandlerInterface;
use Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionResponse;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProviderInterface;

class MassUpdateActionHandler implements MassActionHandlerInterface
{
    const SERVICE_ID = 'trustify_mass_update.mass_action.update_handler';
    const ACTION_NAME = 'mass_update';

    const FLUSH_BATCH_SIZE = 100;

    /** @var DoctrineHelper */
    protected $doctrineHelper;

    /** @var EntityManagerInterface */
    protected $entityManager;

    /** @var string */
    protected $identifierName;

    /** @var string */
    protected $entityName;

    /** @var ConfigProviderInterface */
    protected $gridConfigProvider;

    /** @var TranslatorInterface */
    protected $translator;

    /** @var SecurityFacade */
    protected $securityFacade;

    /**
     * @param DoctrineHelper          $doctrineHelper
     * @param ConfigProviderInterface $gridConfigProvider
     * @param TranslatorInterface     $translator
     * @param SecurityFacade          $securityFacade
     */
    public function __construct(
        DoctrineHelper $doctrineHelper,
        ConfigProviderInterface $gridConfigProvider,
        TranslatorInterface $translator,
        SecurityFacade $securityFacade
    ) {
        $this->doctrineHelper = $doctrineHelper;
        $this->gridConfigProvider = $gridConfigProvider;
        $this->translator = $translator;
        $this->securityFacade = $securityFacade;
    }

    /**
     * @param DatagridInterface $datagrid
     *
     * @return string first root entity
     */
    public static function getEntityNameFromDatagrid(DatagridInterface $datagrid)
    {
        $entityName = null;
        $fromItems = $datagrid->getConfig()->offsetGetByPath('[source][query][from]', false);

        /** @var OrmDatasource $dataSource */
        $dataSource = $datagrid->getDatasource();
        if ($dataSource) {
            /** @var QueryBuilder $queryBuilder */
            $queryBuilder = $dataSource->getQueryBuilder();
            $rootEntities = $queryBuilder->getRootEntities();

            $entityName = reset($rootEntities);
        } elseif (!empty($fromItems)) {
            // grid not built yet
            $entityName = empty($fromItems[0]['table']) ? null : $fromItems[0]['table'];
        }

        return $entityName;
    }

    /**
     * @param DatagridInterface $datagrid
     */
    protected function initAction(DatagridInterface $datagrid)
    {
        $this->entityName = self::getEntityNameFromDatagrid($datagrid);

        $this->entityManager  = $this->doctrineHelper->getEntityManager($this->entityName);
        $this->identifierName = $this->doctrineHelper->getSingleEntityIdentifierFieldName($this->entityName);
    }

    /**
     * @return bool
     */
    protected function isMassActionEnabled()
    {
        if ($this->gridConfigProvider->hasConfig($this->entityName)) {
            return $this->gridConfigProvider->getConfig($this->entityName)->is('update_mass_action_enabled');
        } else {
            // disable mass action by default for not configurable entities
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function handle(MassActionHandlerArgs $args)
    {
        $this->initAction($args->getDatagrid());
        if (!$this->isMassActionEnabled()) {
            return $this->getResponse($args, 0, 'not configured');
        }

        $fieldName   = $args->getData()['mass_edit_field'];
        $value       = $args->getData()[$fieldName];
        $selectedIds = [];

        $entitiesCount = 0;
        $iteration     = 0;
        $results       = $args->getResults();

        if (!$this->securityFacade->isGranted('EDIT', 'entity:' . $this->entityName)) {
            return $this->getResponse($args, $entitiesCount, 'not allowed to modify the entity');
        }

        // batch should be processed in transaction
        $this->entityManager->beginTransaction();
        try {
            // if huge amount data must be deleted
            set_time_limit(0);

            foreach ($results as $result) {
                /** @var $result ResultRecordInterface */
                $selectedIds[] = $result->getValue($this->identifierName);

                $iteration++;

                if ($iteration % self::FLUSH_BATCH_SIZE == 0) {
                    $entitiesCount += $this->finishBatch($selectedIds, $fieldName, $value);
                }
            }

            if ($iteration % self::FLUSH_BATCH_SIZE > 0) {
                $entitiesCount += $this->finishBatch($selectedIds, $fieldName, $value);
            }

            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        return $this->getResponse($args, $entitiesCount);
    }

    /**
     * @param MassActionHandlerArgs $args
     * @param int                   $entitiesCount
     * @param string                $error
     *
     * @return MassActionResponse
     */
    protected function getResponse(MassActionHandlerArgs $args, $entitiesCount, $error = null)
    {
        $options = $args->getMassAction()->getOptions()->toArray();

        $successful = $entitiesCount > 0;
        $message    = $successful ?
            $this->translator->trans($options['success_message'], ['%items%' => $entitiesCount]) :
            $this->translator->trans($options['error_message'], ['%error%' => $error]);

        return new MassActionResponse($successful, $message);
    }

    /**
     * @param array  $selectedIds
     * @param string $fieldName
     * @param string $value
     *
     * @return int
     */
    protected function finishBatch(array &$selectedIds, $fieldName, $value)
    {
        $entityManager = $this->entityManager;
        $qBuilder      = $entityManager->createQueryBuilder();

        $entitiesCount = $qBuilder->update($this->entityName, 'e')
            ->set('e.'.$fieldName, ':value')
            ->where($qBuilder->expr()->in('e.'.$this->identifierName, $selectedIds))
            ->getQuery()
            ->setParameter('value', $value)
            ->execute();

        $entityManager->flush();
        if ($entityManager->getConnection()->getTransactionNestingLevel() == 1) {
            $entityManager->clear();
        }

        // empty buffer
        $selectedIds = [];

        return $entitiesCount;
    }
}
