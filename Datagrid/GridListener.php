<?php

namespace Trustify\Bundle\MassUpdateBundle\Datagrid;

use Oro\Bundle\DataGridBundle\Event\BuildBefore;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityBundle\ORM\EntityClassResolver;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProviderInterface;

use Trustify\Bundle\MassUpdateBundle\Datagrid\MassAction\MassUpdateActionHandler;

/**
 * Add update mass action to entity grids
 * for entities that has update_mass_action_enabled flag enabled
 */
class GridListener
{
    const ACTION_CONFIGURATION_KEY = '[mass_actions][%s]';

    /** @var string */
    protected $entityName;

    /** @var EntityClassResolver */
    protected $entityClassResolver;

    /** @var DoctrineHelper */
    protected $doctrineHelper;

    /** @var ConfigProviderInterface */
    protected $gridConfigProvider;

    /**
     * @param EntityClassResolver     $classResolver
     * @param DoctrineHelper          $doctrineHelper
     * @param ConfigProviderInterface $gridConfigProvider
     */
    public function __construct(
        EntityClassResolver $classResolver,
        DoctrineHelper $doctrineHelper,
        ConfigProviderInterface $gridConfigProvider
    ) {
        $this->entityClassResolver = $classResolver;
        $this->doctrineHelper      = $doctrineHelper;
        $this->gridConfigProvider  = $gridConfigProvider;
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

        if ($this->gridConfigProvider->hasConfig($this->entityName)) {
            $isEnabled = $this->gridConfigProvider->getConfig($this->entityName)->is('update_mass_action_enabled');
        } else {
            // disable mass action by default for not configurable entities
            $isEnabled = false;
        }

        $existsingConfig = $event->getConfig()->offsetGetByPath(
            sprintf(self::ACTION_CONFIGURATION_KEY, MassUpdateActionHandler::ACTION_NAME),
            false
        );

        return empty($existsingConfig) && $isEntity && $isEnabled;
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
                'data_identifier'     => 'e.'.$this->doctrineHelper
                        ->getSingleEntityIdentifierFieldName($this->entityName),
                'handler'             => MassUpdateActionHandler::SERVICE_ID,
                'label'               => 'trustify.mass_update.dialog.title',
                'success_message'     => 'trustify.mass_update.success_message',
                'error_message'       => 'trustify.mass_update.error_message',
            ]
        );
    }
}
