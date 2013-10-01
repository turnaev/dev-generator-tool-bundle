<?php

namespace DevGeneratorToolBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('dev_generator_tool');
        $rootNode
            ->children()
                ->arrayNode('bundle')
                    ->children()
                        ->scalarNode('core_path')->defaultValue('LP/CoreBundle')->end()
                        ->scalarNode('web_path')->defaultValue('LP/WebBundle')->end()
                    ->end()
                ->end()
            ->scalarNode('generate_translation')->defaultValue(false)->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
