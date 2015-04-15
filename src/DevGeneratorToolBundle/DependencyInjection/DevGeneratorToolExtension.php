<?php

namespace DevGeneratorToolBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class DevGeneratorToolExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        //generate_translation
        $container->setParameter('dev_generator_tool.generate_translation', $config['generate_translation']);

        //dir_skeleton
        $container->setParameter('dev_generator_tool.dir_skeleton', $config['dir_skeleton']);

        //core
        $container->setParameter('dev_generator_tool.bundle.core.path', $config['bundle']['core_path']);

        $bundleName = str_replace('/', '', $config['bundle']['core_path']);
        $container->setParameter('dev_generator_tool.bundle.core.name', $bundleName);

        $bundleNs = str_replace('/', '\\', $config['bundle']['core_path']);
        $container->setParameter('dev_generator_tool.bundle.core.ns', $bundleNs);

        $baseNs = explode('\\', $bundleNs)[0];
        $container->setParameter('dev_generator_tool.bundle.core.base_ns', $baseNs);

        //web
        $container->setParameter('dev_generator_tool.bundle.web.path', $config['bundle']['web_path']);

        $bundleName = str_replace('/', '', $config['bundle']['web_path']);
        $container->setParameter('dev_generator_tool.bundle.web.name', $bundleName);

        $bundleNs = str_replace('/', '\\', $config['bundle']['web_path']);
        $container->setParameter('dev_generator_tool.bundle.web.ns', $bundleNs);

        $baseNs = explode('\\', $bundleNs)[0];
        $container->setParameter('dev_generator_tool.bundle.web.base_ns', $baseNs);
    }
}
