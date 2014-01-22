<?php

namespace DevGeneratorToolBundle\Generator;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

/**
 * Generates a CRUD controller.
 *
 */
class DoctrineCrudGenerator extends Generator
{
    protected $filesystem;
    protected $skeletonDir;
    protected $baseRoutePrefix;
    protected $routePrefix;
    protected $nameEntity;
    protected $routeNamePrefix;
    protected $bundle;
    protected $entity;
    protected $metadata;
    protected $format;
    protected $actions;
    protected $container;
    protected $src;
    protected $outputBundle;
    private $fieldMappings = array();

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
    public function setSrc($src) {
        $this->src = $src;
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
     * Generate the CRUD controller.
     *
     * @param BundleInterface   $bundle           A bundle object
     * @param string            $entity           The entity relative class name
     * @param ClassMetadataInfo $metadata         The entity class metadata
     * @param string            $format           The configuration format (xml, yaml, annotation)
     * @param string            $routePrefix      The route name prefix
     * @param array             $needWriteActions Wether or not to generate write actions
     *
     * @throws \RuntimeException
     */
    public function generate(BundleInterface $bundle, $entity, ClassMetadataInfo $metadata, $format, $routePrefix, $needWriteActions, $forceOverwrite)
    {
        $this->baseRoutePrefix = $routePrefix;
        $this->nameEntity  = strtolower($entity);
        $this->routePrefix = $routePrefix.'.'.$this->nameEntity;
        $this->routeNamePrefix = str_replace('/', '_', $routePrefix);
        $this->actions = $needWriteActions ? array('list', 'show', 'new', 'edit', 'delete') : array('list', 'show');

        if (count($metadata->identifier) > 1) {
            throw new \RuntimeException('The CRUD generator does not support entity classes with multiple primary keys.');
        }

        if (!in_array('id', $metadata->identifier)) {
            throw new \RuntimeException('The CRUD generator expects the entity object has a primary key field named "id" with a getId() method.');
        }

        $this->entity   = $entity;
        $this->bundle   = $bundle;
        $this->metadata = $metadata;
        $this->setFormat($format);

        if(is_null($this->src)) {
            $this->src = $this->bundle->getPath();
        }

        if(is_null($this->outputBundle)) {
            $this->outputBundle = $this->bundle->getName();
        }

        $this->generateControllerClass($forceOverwrite);

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

        if($this->getContainer()->getParameter('dev_generator_tool.generate_translation')) {
            $this->generateTranslation(sprintf('%s/Resources/translations', $this->src));
        }

        $this->generateConfiguration();
    }

    /**
     * Sets the configuration format.
     *
     * @param string $format The configuration format
     */
    private function setFormat($format)
    {
        switch ($format) {
            case 'yml':
            case 'xml':
            case 'php':
            case 'annotation':
                $this->format = $format;
                break;
            default:
                $this->format = 'yml';
                break;
        }
    }

    /**
     * Generates the routing configuration.
     *
     */
    protected function generateConfiguration()
    {
        if (!in_array($this->format, array('yml', 'xml', 'php'))) {
            return;
        }

        $name = str_replace('\\', '_', $this->entity);
        $name = preg_replace('/(.)([A-Z])/', '\1-\2', $name);
        $name = strtolower($name);

        $target = sprintf(
            '%s/Resources/config/routing/%s.%s',
            $this->src,
            $name,
            $this->format
        );

        $baseNs = $this->getContainer()->getParameter('dev_generator_tool.bundle.web.base_ns');

        $options = [
            'actions'           => $this->actions,
            'route_prefix'      => $this->routePrefix,
            'bundle'            => $baseNs.$this->outputBundle,
            'entity'            => $this->entity,
        ];

        $this->renderFile($this->skeletonDir, 'config/routing.'.$this->format.'.twig', $target, $options);
    }

    /**
     * Generates the controller class only.
     *
     */
    protected function generateControllerClass($forceOverwrite)
    {
        $dir = $this->src;

        $parts = explode('\\', $this->entity);
        $entityClass = array_pop($parts);
        $entityNamespace = implode('\\', $parts);

        $target = sprintf(
            '%s/Controller/%s/%sController.php',
            $dir,
            str_replace('\\', '/', $entityNamespace),
            $entityClass
        );

        if (!$forceOverwrite && file_exists($target)) {
            throw new \RuntimeException('Unable to generate the controller as it already exists.');
        }

        $fieldMappings = $this->getFieldMappings();

        $maxColumnNameSize = 0;
        foreach($fieldMappings as $fieldMapping) {
            $maxColumnNameSize = max($maxColumnNameSize, $fieldMapping['columnNameSize']);
        }

        $baseNs = $this->getContainer()->getParameter('dev_generator_tool.bundle.web.base_ns');

        $options= [

                'actions'           => $this->actions,
                'base_route_prefix' => $this->baseRoutePrefix,
                'route_prefix'      => $this->routePrefix,
                'name_entity'       => $this->nameEntity,
                'dir'               => $this->skeletonDir,
                'bundle'            => $this->bundle->getName(),
                'entity'            => $this->entity,
                'fields'            => $fieldMappings,
                'entity_class'      => $entityClass,
                'namespace'         => $baseNs.'\\'.$this->outputBundle,
                'entity_namespace'  => $entityNamespace,
                'format'            => $this->format,
                'webBundleNs'       => $this->getContainer()->getParameter('dev_generator_tool.bundle.web.ns'),
                'coreBundleNs'      => $this->getContainer()->getParameter('dev_generator_tool.bundle.core.ns'),
                'maxColumnNameSize' => $maxColumnNameSize,
        ];
        $this->renderFile($this->skeletonDir, 'controller.php.twig', $target, $options);
    }

    protected function generateTranslation($dir)
    {
        if (!file_exists($dir)) {
            $this->filesystem->mkdir($dir, 0777);
        }

        $fieldMappings = $this->getFieldMappings();
        $trans = [];
        foreach($fieldMappings as $fieldMapping) {
            if($fieldMapping['fieldName'] != 'id') {
                $trans[$fieldMapping['fieldName']] = $fieldMapping['label'];
            }
        }

        $gt = new \DevConsoleToolBundle\Translater\GoogleTranslater();

        foreach(['ru', 'en'] as $locale) {
            $file = sprintf('%s/entity_%s.%s.yml', $dir, $this->entity, $locale);

            if(!file_exists($file)) {
                file_put_contents($file, "#Localization file for the entity {$this->entity}. Locale {$locale}.\n");
            }
            $comments = array_filter(file($file), function($str) {
                    return preg_match('/^#/', $str);
                });
            $comments = join("\n", $comments);

            if($locale == 'ru') {

                $translationsArr = \Symfony\Component\Yaml\Yaml::parse($file);

                $translationsArr = $translationsArr ? $translationsArr : [];

                foreach($trans as $key=>$tran) {

                    if(!isset($translationsArr[$key])) {

                        $gtTran = $gt->translateText($tran, $fromLanguage = 'en', $toLanguage = 'ru');

                        if(!$gt->getErrors()) {
                            $tran = $gtTran;
                        } else {
                            echo 'Translator error. '.$gt->getErrors();
                        }

                        $tran = ucfirst($tran);
                        $translationsArr[$key] = $tran;
                    }
                }

            } else {

                $translationsArr = \Symfony\Component\Yaml\Yaml::parse($file);
                $translationsArr = $translationsArr ? $translationsArr : [];
                $translationsArr  = array_merge($trans, $translationsArr);
            }

            ksort($translationsArr);
            if($translationsArr) {
                $translationsYml = \Symfony\Component\Yaml\Yaml::dump($translationsArr);
            } else {
                $translationsYml = '';
            }

            file_put_contents($file, $comments.$translationsYml);
        }
    }

    /**
     * Generates the list.html.twig template in the final bundle.
     *
     * @param string $dir The path to the folder that hosts templates in the bundle
     */
    protected function generateIndexView($dir)
    {
        $fieldMappings = $this->getFieldMappings();

        $maxColumnNameSize = 0;
        foreach($fieldMappings as $fieldMapping) {
            $maxColumnNameSize = max($maxColumnNameSize, $fieldMapping['columnNameSize']);
        }

        $this->renderFile($this->skeletonDir, 'views/list.html.twig.twig', $dir.'/Crud/list.html.twig', array(
            'dir'               => $this->skeletonDir,
            'entity'            => $this->entity,
            'fields'            => $fieldMappings,
            'actions'           => $this->actions,
            'record_actions'    => $this->getRecordActions(),
            'route_prefix'      => $this->routePrefix,
            'maxColumnNameSize' => $maxColumnNameSize,
            'parentBundle'      => $this->getContainer()->getParameter('dev_generator_tool.bundle.web.name')
        ));
    }


    private function getFieldMappings()
    {
        if(!$this->fieldMappings) {

            $this->fieldMappings = $this->metadata->fieldMappings;

            foreach($this->fieldMappings as &$fieldMapping) {
                $fieldMapping['label'] = ucfirst(preg_replace('/_/', ' ', $fieldMapping['columnName']));
                $fieldMapping['columnNameSize'] = strlen($fieldMapping['columnName']);
            }

            ksort($this->fieldMappings);
            if(isset($this->fieldMappings['id'])) {
                $idField = $this->fieldMappings['id'];
                unset($this->fieldMappings['id']);
                $this->fieldMappings = array_merge(['id'=>$idField], $this->fieldMappings);
            }
        }
        return $this->fieldMappings;
    }
    /**
     * Generates the show.html.twig template in the final bundle.
     *
     * @param string $dir The path to the folder that hosts templates in the bundle
     */
    protected function generateShowView($dir)
    {
        $fieldMappings = $this->getFieldMappings();

        $maxColumnNameSize = 0;
        foreach($fieldMappings as $fieldMapping) {
            $maxColumnNameSize = max($maxColumnNameSize, $fieldMapping['columnNameSize']);
        }

        $this->renderFile($this->skeletonDir, 'views/show.html.twig.twig', $dir.'/Crud/show.html.twig', array(
            'dir'               => $this->skeletonDir,
            'entity'            => $this->entity,
            'fields'            => $fieldMappings,
            'actions'           => $this->actions,
            'route_prefix'      => $this->routePrefix,
            'maxColumnNameSize' => $maxColumnNameSize,
            'parentBundle'      => $this->getContainer()->getParameter('dev_generator_tool.bundle.web.name')
        ));
    }

    /**
     * Generates the new.html.twig template in the final bundle.
     *
     * @param string $dir The path to the folder that hosts templates in the bundle
     */
    protected function generateNewView($dir)
    {
        $this->renderFile($this->skeletonDir, 'views/create.html.twig.twig', $dir.'/Crud/create.html.twig', array(
            'dir'               => $this->skeletonDir,
            'route_prefix'      => $this->routePrefix,
            'entity'            => $this->entity,
            'actions'           => $this->actions,
            'parentBundle'      => $this->getContainer()->getParameter('dev_generator_tool.bundle.web.name')
        ));
    }

    /**
     * Generates the edit.html.twig template in the final bundle.
     *
     * @param string $dir The path to the folder that hosts templates in the bundle
     */
    protected function generateEditView($dir)
    {
        $this->renderFile($this->skeletonDir, 'views/edit.html.twig.twig', $dir.'/Crud/edit.html.twig', array(
            'dir'               => $this->skeletonDir,
            'route_prefix'      => $this->routePrefix,
            'entity'            => $this->entity,
            'actions'           => $this->actions,
            'parentBundle'      => $this->getContainer()->getParameter('dev_generator_tool.bundle.web.name')
        ));
    }

    /**
     * Returns an array of record actions to generate (edit, show).
     *
     * @return array
     */
    protected function getRecordActions()
    {
        return array_filter($this->actions, function($item) {
            return in_array($item, array('show', 'edit'));
        });
    }
}
