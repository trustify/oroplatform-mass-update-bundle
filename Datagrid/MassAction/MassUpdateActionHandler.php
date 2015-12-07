<?php

namespace Trustify\Bundle\MassUpdateBundle\Datagrid\MassAction;

use Symfony\Component\Translation\TranslatorInterface;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use Oro\Bundle\DataGridBundle\Extension\MassAction\Actions\MassActionInterface;
use Oro\Bundle\SecurityBundle\SecurityFacade;
use Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionHandlerArgs;
use Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionHandlerInterface;
use Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionResponse;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProviderInterface;

class MassUpdateActionHandler implements MassActionHandlerInterface, LoggerAwareInterface
{
    const SERVICE_ID  = 'trustify_mass_update.mass_action.update_handler';
    const ACTION_NAME = 'mass_update';

    /** @var string */
    protected $entityName;

    /** @var ConfigProviderInterface */
    protected $gridConfigProvider;

    /** @var TranslatorInterface */
    protected $translator;

    /** @var SecurityFacade */
    protected $securityFacade;

    /** @var ActionRepository */
    protected $actionRepository;

    /** @var LoggerInterface */
    protected $logger;

    /**
     * @param ConfigProviderInterface $gridConfigProvider
     * @param TranslatorInterface     $translator
     * @param SecurityFacade          $securityFacade
     * @param ActionRepository        $actionRepository
     */
    public function __construct(
        ConfigProviderInterface $gridConfigProvider,
        TranslatorInterface $translator,
        SecurityFacade $securityFacade,
        ActionRepository $actionRepository
    ) {
        $this->gridConfigProvider = $gridConfigProvider;
        $this->translator = $translator;
        $this->securityFacade = $securityFacade;
        $this->actionRepository = $actionRepository;
        $this->logger = new NullLogger();
    }

    /**
     * @param null|string $entityName
     *
     * @return bool
     */
    public function isMassActionEnabled($entityName)
    {
        $isEnabled = false;
        if ($entityName && $this->gridConfigProvider->hasConfig($entityName)) {
            $isEnabled = $this->gridConfigProvider->getConfig($entityName)->is('update_mass_action_enabled');
        } else {
            $this->logger->debug("Update Mass Action: not configured for " . $entityName);
        }

        if ($isEnabled && $this->securityFacade->isGranted('EDIT', 'entity:' . $entityName)) {
            $isEnabled = true;
        } elseif ($isEnabled) {
            $this->logger->debug("Update Mass Action: not allowed to modify the entity of class " . $entityName);
            $isEnabled = false;
        }

        return $isEnabled;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(MassActionHandlerArgs $args)
    {
        $massAction = $args->getMassAction();

        $entityName = $this->actionRepository->getEntityName($args->getDatagrid());
        if (!$this->isMassActionEnabled($entityName)) {
            return $this->getResponse($massAction, 0, 'Action not configured or not allowed');
        }

        $massAction->getOptions()->offsetSet('entityName', $entityName);
        $entitiesCount = $this->actionRepository->batchUpdate($massAction, $args->getResults(), $args->getData());

        return $this->getResponse($massAction, $entitiesCount);
    }

    /**
     * @param MassActionInterface $massAction
     * @param int                 $entitiesCount
     * @param string              $error
     *
     * @return MassActionResponse
     */
    protected function getResponse(MassActionInterface $massAction, $entitiesCount, $error = null)
    {
        $options = $massAction->getOptions()->toArray();

        $successful = $entitiesCount > 0;
        $message    = $successful ?
            $this->translator->trans($options['success_message'], ['%items%' => $entitiesCount]) :
            $this->translator->trans($options['error_message'], ['%error%' => $error]);

        return new MassActionResponse($successful, $message);
    }

    /**
     * {@inheritdoc}
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
