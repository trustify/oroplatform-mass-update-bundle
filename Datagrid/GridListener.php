<?php

namespace Trustify\Bundle\MassUpdateBundle\Datagrid;

use Oro\Bundle\DataGridBundle\Event\BuildBefore;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityBundle\ORM\EntityClassResolver;

use Trustify\Bundle\MassUpdateBundle\Datagrid\MassAction\MassUpdateActionHandler;

class GridListener
{
    const ACTION_CONFIGURATION_KEY = '[mass_actions][%s]';

    /** @var string */
    protected $entityName;

    /** @var EntityClassResolver */
    protected $entityClassResolver;

    /** @var DoctrineHelper */
    private $doctrineHelper;

    /**
     * @param EntityClassResolver $classResolver
     * @param DoctrineHelper      $doctrineHelper
     */
    public function __construct(EntityClassResolver $classResolver, DoctrineHelper $doctrineHelper)
    {
        $this->entityClassResolver = $classResolver;
        $this->doctrineHelper      = $doctrineHelper;
    }

    /**
     * @param BuildBefore $event
     *
     * @return bool
     */
    protected function isApplicable(BuildBefore $event)
    {
        $this->entityName = MassUpdateActionHandler::getEntityNameFromDatagrid($event->getDatagrid());

        try {
            $isEntity = $this->entityClassResolver->isEntity($this->entityName);
        } catch (\Exception $e) {
            $isEntity = false;
        }

        $isNotConfigured = $event->getConfig()->offsetGetByPath(
            sprintf(self::ACTION_CONFIGURATION_KEY, MassUpdateActionHandler::ACTION_NAME),
            true
        );

        // TODO: check if mass action enabled for the grid and entityName
        $gridName = $event->getDatagrid()->getName();

        return $isNotConfigured && $isEntity;
    }

    /**
     * {@inheritdoc}
     */
    public function onBuildBefore(BuildBefore $event)
    {
        if (!$this->isApplicable($event)) {
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
                    'route_parameters' => ['entityName' => str_replace('\\', '_', $this->entityName)],
                ],
                'data_identifier'     => 'e.' . $this->doctrineHelper->getSingleEntityIdentifierFieldName(
                    $this->entityName
                ),
                'handler'             => MassUpdateActionHandler::SERVICE_ID,
                // TODO: translate
                'label'               => 'Bulk Edit',
            ]
        );
    }
}
