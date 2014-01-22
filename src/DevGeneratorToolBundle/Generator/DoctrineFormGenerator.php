<?php


namespace DevGeneratorToolBundle\Generator;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

/**
 * Generates a form class based on a Doctrine entity.
 *
 */
class DoctrineFormGenerator extends Generator
{
    private $filesystem;
    private $skeletonDir;
    private $className;
    private $classPath;
    protected $src;
    protected $outputBundle;

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

    public function __construct(Filesystem $filesystem, $skeletonDir)
    {
        $this->filesystem = $filesystem;
        $this->skeletonDir = $skeletonDir;
    }

    public function getClassName()
    {
        return $this->className;
    }

    public function getClassPath()
    {
        return $this->classPath;
    }

    /**
     * Generates the entity form class if it does not exist.
     *
     * @param BundleInterface   $bundle   The bundle in which to create the class
     * @param string            $entity   The entity relative class name
     * @param ClassMetadataInfo $metadata The entity metadata class
     */
    public function generate(BundleInterface $bundle, $entity, ClassMetadataInfo $metadata)
    {
        $this->nameEntity  = strtolower($entity);

        if(is_null($this->src)) {
            $this->src = $bundle->getPath();
        }

        if(is_null($this->outputBundle)) {
            $this->outputBundle = $bundle->getName();
        }

        $baseNS = explode('\\', $bundle->getNamespace())[0];
        $bundleNamespaceTarget = $baseNS.'\\'.$this->outputBundle;


        $parts       = explode('\\', $entity);
        $entityClass = array_pop($parts);

        $this->className = $entityClass;
        $dirPath         = $this->src.'/Form/Type';
        $this->classPath = $dirPath.'/'.str_replace('\\', '/', $entity).'FormType.php';

        if (file_exists($this->classPath)) {
            throw new \RuntimeException(sprintf('Unable to generate the %s form class as it already exists under the %s file', $this->className, $this->classPath));
        }

        if (count($metadata->identifier) > 1) {
            throw new \RuntimeException('The form generator does not support entity classes with multiple primary keys.');
        }

        $parts = explode('\\', $entity);
        array_pop($parts);

        $fields           = $this->getFieldsFromMetadata($metadata);
        $maxColumnNameSize = 0;
        foreach($fields as $field) {
            $maxColumnNameSize = max(strlen($field)+2, $maxColumnNameSize);
        }

        $this->renderFile($this->skeletonDir, 'FormType.php.twig', $this->classPath, array(
            'dir'              => $this->skeletonDir,
            'fields'           => $fields,
            'namespace'        => $bundle->getNamespace(),
            'bundel_namespace_target'   => $bundleNamespaceTarget,
            'entity_namespace' => implode('\\', $parts),
            'entity_class'     => $entityClass,
            'form_class'       => $this->className.'FormType',
            'form_label'       => $entityClass,
            'name_entity'   => $this->nameEntity,
            'maxColumnNameSize' => $maxColumnNameSize,
        ));
    }

    /**
     * Returns an array of fields. Fields can be both column fields and
     * association fields.
     *
     * @param ClassMetadataInfo $metadata
     * @return array $fields
     */
    private function getFieldsFromMetadata(ClassMetadataInfo $metadata)
    {
        $fields = (array) $metadata->fieldNames;

        // Remove the primary key field if it's not managed manually
        if (!$metadata->isIdentifierNatural()) {
            $fields = array_diff($fields, $metadata->identifier);
        }

        foreach ($metadata->associationMappings as $fieldName => $relation) {
            if ($relation['type'] !== ClassMetadataInfo::ONE_TO_MANY) {
                $fields[] = $fieldName;
            }
        }
        asort($fields);

        return $fields;
    }
}
