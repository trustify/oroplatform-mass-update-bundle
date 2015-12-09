<?php

namespace Trustify\Bundle\MassUpdateBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use Oro\Bundle\ConfigBundle\DependencyInjection\SettingsBuilder;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('trustify_mass_update');

        $rootNode
            ->children()
                ->arrayNode('mapping')
                    ->cannotBeEmpty()
                    ->cannotBeOverwritten(false)
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->cannotBeEmpty()
                        ->cannotBeOverwritten(false)
                        ->useAttributeAsKey('name')
                        ->prototype('array')
                            ->children()
                                ->scalarNode('type')->end()
                                ->arrayNode('options')
                                    ->prototype('variable')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        SettingsBuilder::append(
            $rootNode,
            [
                'field_acl_enabled' => ['type' => 'boolean', 'value' => false],
            ]
        );

        return $treeBuilder;
    }
}
