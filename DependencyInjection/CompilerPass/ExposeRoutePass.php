<?php

namespace Trustify\Bundle\MassUpdateBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ExposeRoutePass implements CompilerPassInterface
{
    const ROUTING_EXTRACTOR = 'fos_js_routing.extractor';

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition(self::ROUTING_EXTRACTOR)) {
            return;
        }

        $extractorDefinition = $container->getDefinition(self::ROUTING_EXTRACTOR);

        $exposedRoutes = $extractorDefinition->getArgument(1);
        $exposedRoutes[] = 'trustify_mass_update';

        $extractorDefinition->replaceArgument(1, $exposedRoutes);
    }
}
