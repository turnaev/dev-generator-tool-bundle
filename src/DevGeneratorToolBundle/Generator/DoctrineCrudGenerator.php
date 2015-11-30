<?php

namespace DevGeneratorToolBundle\Generator;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

/**
 * Generates a CRUD controller.
 */
class DoctrineCrudGenerator extends Generator
{
    protected $filesystem;
    protected $skeletonDir;

    protected $tplOptions = [];
    protected $bundle;
    protected $entityBundle;
    protected $entity;
    protected $entityName;
    protected $metadata;
    protected $actions;
    protected $container;
    protected $src;
    protected $outputBundle;

    /**
     * @param mixed $entityBundle
     */
    public function setEntityBundle($entityBundle)
    {
        $this->entityBundle = $entityBundle;
    }

    /**
     * Constructor.
     *
     * @param Filesystem $filesystem  A Filesystem instance
     * @param string     $skeletonDir Path to the skeleton directory
     */
    public function __construct(Filesystem $filesystem, $skeletonDir)
    {
        $this->filesystem  = $filesystem;
        $this->skeletonDir = $skeletonDir;
    }

    /**
     * @param mixed $outputBundle
     */
    public function setOutputBundle($outputBundle)
    {
        $this->outputBundle = $outputBundle;
    }

    /**
     * @param string $src
     */
    public function setSrc($src)
    {
        $this->src = $src;
    }

    /**
     * @return array
     */
    public function getTplOptions()
    {
        return $this->tplOptions;
    }

    /**
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     */
    public function setContainer(\Symfony\Component\DependencyInjection\ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return \Symfony\Component\DependencyInjection\ContainerInterface $container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Generate the CRUD controller.
     *
     * @param BundleInterface   $entityBundle           A bundle object
     * @param string            $entity           The entity relative class name
     * @param ClassMetadataInfo $metadata         The entity class metadata
     * @param string            $routePrefix      The route name prefix
     * @param array             $needWriteActions Wether or not to generate write actions
     *
     * @throws \RuntimeException
     */
    public function generate($bundle, $entity, ClassMetadataInfo $metadata, $routePrefix, $needWriteActions)
    {
        $this->bundle   = $bundle;

        $this->metadata = $metadata;
        $this->actions = $needWriteActions ? ['list', 'show', 'new', 'edit', 'delete'] : ['list', 'show'];
        $this->entity   = $entity;

        $str  = preg_replace('/([A-Z])/', ' \1', $entity);
        $str = trim($str);
        $str = strtolower($str);
        $this->entityName = str_replace(' ', '_', $str);

        if (count($metadata->identifier) > 1) {
            throw new \RuntimeException('The CRUD generator does not support entity classes with multiple primary keys.');
        }

        if (!in_array('id', $metadata->identifier)) {
            throw new \RuntimeException('The CRUD generator expects the entity object has a primary key field named "id" with a getId() method.');
        }

        if (is_null($this->src)) {
            $this->src = $this->bundle->getPath();
        }

        if (is_null($this->outputBundle)) {
            $this->outputBundle = $this->bundle->getName();
        }

        $this->initTplOptions($routePrefix);

        $this->generateControllerClass();

        $dirViews = sprintf('%s/Resources/views/%s', $this->src, str_replace('\\', '/', $this->entity));
        if (!file_exists($dirViews)) {
            $this->filesystem->mkdir($dirViews, 0777);
        }
        $this->generateIndexView($dirViews);

        if (in_array('show', $this->actions)) {
            $this->generateShowView($dirViews);
        }

        if (in_array('new', $this->actions)) {
            $this->generateNewView($dirViews);
        }

        if (in_array('edit', $this->actions)) {
            $this->generateEditView($dirViews);
        }


        if ($this->getContainer()->getParameter('dev_generator_tool.generate_translation')) {

            $fieldMappings = $this->getFieldMappings();
            $g = new TranslationGenerator($this->filesystem, sprintf('%s/Resources/translations', $this->src), $entity, $fieldMappings);
            $g->generate();
        }

        $this->generateConfiguration();
    }

    /**
     * @param $routePrefix
     */
    public function initTplOptions($routePrefixBase)
    {
        $routePrefix     = $routePrefixBase.'.'.$this->entityName;

        $fieldMappings = $this->getFieldMappings();
        $maxColumnNameSize = 0;
        foreach ($fieldMappings as $fieldMapping) {
            $maxColumnNameSize = max($maxColumnNameSize, $fieldMapping['columnNameSize']);
        }

        $baseNs = $this->getContainer()->getParameter('dev_generator_tool.bundle.web.base_ns');

        $entityBundleNs = $this->entityBundle;

        $entityBundleNs = preg_replace(
            ['/^Common/', '/^App/'],
            ['Common\\',  'App\\'],
            $entityBundleNs);

        $backendBundleNs = preg_replace('/\//', '\\', $this->outputBundle);
        $backendBundle = preg_replace('/\\\/', '', $backendBundleNs);

        $this->tplOptions = [
            'fields'               => $fieldMappings,

            'actions'              => $this->actions,
            'record_actions'       => $this->getRecordActions(),

            'max_column_name_size' => $maxColumnNameSize,

            'route_prefix_base'    => $routePrefixBase,
            'route_prefix'         => $routePrefix,

            'entity_bundle'        => $this->entityBundle,
            'entity_bundle_ns'     => $entityBundleNs,
            'entity'               => $this->entity,
            'entity_name'          => $this->entityName,

            'core_bundle_ns'       => $this->getContainer()->getParameter('dev_generator_tool.bundle.core.ns'),
            'core_bundle'          => $this->getContainer()->getParameter('dev_generator_tool.bundle.core.name'),

            'web_bundle_ns'        => $this->getContainer()->getParameter('dev_generator_tool.bundle.web.ns'),
            'web_bundle'           => $this->getContainer()->getParameter('dev_generator_tool.bundle.web.name'),

            'backend_bundle_ns'    => $backendBundleNs,
            'backend_bundle'       => $backendBundle,
        ];
    }

    /**
     * Generates the controller class only.
     */
    protected function generateControllerClass()
    {
        $target = sprintf(
            '%s/Controller/%sController.php',
            $this->src,
            $this->entity
        );

        $this->renderFile($this->skeletonDir, 'controller.php.twig', $target, $this->tplOptions);
    }

    /**
     * Generates the routing configuration.
     */
    protected function generateConfiguration()
    {
        $target = sprintf(
            '%s/Resources/config/routing/%s.xml',
            $this->src,
            $this->entityName
        );

        $this->renderFile($this->skeletonDir.'/..', 'config/routing.xml.twig', $target, $this->tplOptions);

        $target = sprintf(
            '%s/Resources/config/routing.xml',
            $this->src
        );

        if (!file_exists($target)) {
            $this->renderFile($this->skeletonDir.'/..', 'config/routings.xml.twig', $target, $this->tplOptions);
        }

        $routings = file_get_contents($target);

        $key = "/{$this->tplOptions['entity_name']}.xml";
        if (!strpos($routings, $key)) {

            $routing = $this->render($this->skeletonDir.'/..', 'config/routing_item.xml.twig', $this->tplOptions);
            $routings = str_replace("\n</routes>", $routing . "\n\n</routes>", $routings);
            file_put_contents($target, $routings);
        }
    }

    /**
     * Generates the list.html.twig template in the final bundle.
     *
     * @param string $dir The path to the folder that hosts templates in the bundle
     */
    protected function generateIndexView($dir)
    {
        $this->renderFile($this->skeletonDir, 'views/list.html.twig.twig', $dir.'/list.html.twig', $this->tplOptions);
    }

    /**
     * Generates the show.html.twig template in the final bundle.
     *
     * @param string $dir The path to the folder that hosts templates in the bundle
     */
    protected function generateShowView($dir)
    {
        $this->renderFile($this->skeletonDir, 'views/show.html.twig.twig', $dir.'/show.html.twig', $this->tplOptions);
    }

    /**
     * Generates the new.html.twig template in the final bundle.
     *
     * @param string $dir The path to the folder that hosts templates in the bundle
     */
    protected function generateNewView($dir)
    {
        $this->renderFile($this->skeletonDir, 'views/create.html.twig.twig', $dir.'/create.html.twig', $this->tplOptions);
    }

    /**
     * Generates the edit.html.twig template in the final bundle.
     *
     * @param string $dir The path to the folder that hosts templates in the bundle
     */
    protected function generateEditView($dir)
    {
        $this->renderFile($this->skeletonDir, 'views/edit.html.twig.twig', $dir.'/edit.html.twig', $this->tplOptions);
    }

    /**
     * Returns an array of record actions to generate (edit, show).
     *
     * @return array
     */
    protected function getRecordActions()
    {
        return array_filter($this->actions, function ($item) {
            return in_array($item, ['show', 'edit']);
        });
    }

    private function getFieldMappings()
    {
        $fieldMappings = $this->metadata->fieldMappings;

        foreach ($fieldMappings as &$fieldMapping) {
            $fieldMapping['label'] = ucfirst(preg_replace('/_/', ' ', $fieldMapping['columnName']));
            $fieldMapping['columnNameSize'] = strlen($fieldMapping['columnName']) + 1;
        }

        ksort($fieldMappings);
        if (isset($fieldMappings['id'])) {
            $idField = $fieldMappings['id'];
            unset($fieldMappings['id']);
            $fieldMappings = array_merge(['id' => $idField], $fieldMappings);
        }

        return $fieldMappings;
    }
}
