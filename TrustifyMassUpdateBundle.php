<?php

namespace Trustify\Bundle\MassUpdateBundle;

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

use Trustify\Bundle\MassUpdateBundle\DependencyInjection\CompilerPass\ExposeRoutePass;

class TrustifyMassUpdateBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new ExposeRoutePass(), PassConfig::TYPE_AFTER_REMOVING);
    }
}
