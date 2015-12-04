<?php

namespace Trustify\Bundle\MassUpdateBundle\Datagrid;

use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\DataGridBundle\Event\BuildBefore;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityBundle\ORM\EntityClassResolver;

use Trustify\Bundle\MassUpdateBundle\Datagrid\MassAction\MassUpdateActionHandler;

/**
 * Add update mass action to entity grids
 * for entities that has update_mass_action_enabled flag enabled
 */
class GridListener
{
    const ACTION_CONFIGURATION_KEY = '[mass_actions][%s]';
    const FROM_PATH = '[source][query][from]';

    /** @var EntityClassResolver */
    protected $entityClassResolver;

    /** @var DoctrineHelper */
    protected $doctrineHelper;

    /** @var MassUpdateActionHandler */
    protected $actionHandler;

    /**
     * @param EntityClassResolver     $classResolver
     * @param DoctrineHelper          $doctrineHelper
     * @param MassUpdateActionHandler $actionHandler
     */
    public function __construct(
        EntityClassResolver $classResolver,
        DoctrineHelper $doctrineHelper,
        MassUpdateActionHandler $actionHandler
    ) {
        $this->entityClassResolver = $classResolver;
        $this->doctrineHelper      = $doctrineHelper;
        $this->actionHandler       = $actionHandler;
    }

    /**
     * @param BuildBefore $event
     * @param string      $entityName
     *
     * @return bool
     */
    protected function isApplicable(BuildBefore $event, $entityName)
    {
        if (empty($entityName)) {
            return false;
        }

        try {
            $isEntity = $this->entityClassResolver->isEntity($entityName);
        } catch (\Exception $e) {
            $isEntity = false;
        }

        $existingConfig = $event->getConfig()->offsetGetByPath(
            sprintf(self::ACTION_CONFIGURATION_KEY, MassUpdateActionHandler::ACTION_NAME),
            false
        );

        return empty($existingConfig) && $isEntity && $this->actionHandler->isMassActionEnabled($entityName);
    }

    /**
     * @param DatagridInterface $datagrid
     *
     * @return array
     */
    protected function getEntityNameWithAlias(DatagridInterface $datagrid)
    {
        $fromItems = $datagrid->getConfig()->offsetGetByPath('[source][query][from]', false);

        if (empty($fromItems[0]['table'])) {
            return [$datagrid->getParameters()->get('class_name'), null];
        } else {
            $alias = empty($fromItems[0]['alias']) ? null : $fromItems[0]['alias'];

            return [$fromItems[0]['table'], $alias];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onBuildBefore(BuildBefore $event)
    {
        $datagrid = $event->getDatagrid();

        list ($entityName, $alias) = $this->getEntityNameWithAlias($datagrid);
        $alias = $alias ? : 'e';

        if (!$this->isApplicable($event, $entityName)) {
            return;
        }

        // configure mass action
        $event->getConfig()->offsetSetByPath(
            sprintf(self::ACTION_CONFIGURATION_KEY, MassUpdateActionHandler::ACTION_NAME),
            [
                'type'                => 'window',
                'frontend_type'       => 'update-mass',
                'route'               => 'oro_datagrid_mass_action',
                'dialogWindowOptions' => [
                    'route'            => 'trustify_mass_update',
                    'route_parameters' => ['entityName' => str_replace('\\', '_', $entityName)],
                ],
                'data_identifier'     => $alias . '.' . $this->doctrineHelper
                        ->getSingleEntityIdentifierFieldName($entityName),
                'handler'             => MassUpdateActionHandler::SERVICE_ID,
                'label'               => 'trustify.mass_update.dialog.title',
                'success_message'     => 'trustify.mass_update.success_message',
                'error_message'       => 'trustify.mass_update.error_message',
            ]
        );
    }
}
