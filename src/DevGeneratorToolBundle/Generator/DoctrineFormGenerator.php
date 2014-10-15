<?php


namespace DevGeneratorToolBundle\Generator;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

/**
 * Generates a form class based on a Doctrine entity.
 */
class DoctrineFormGenerator extends Generator
{
    private $filesystem;
    private $skeletonDir;
    private $classPath;
    protected $tplOptions = [];
    protected $src;
    protected $entity;

    public function __construct(Filesystem $filesystem, $skeletonDir)
    {
        $this->filesystem = $filesystem;
        $this->skeletonDir = $skeletonDir;
    }

    /**
     * @param string $src
     */
    public function setSrc($src) {
        $this->src = $src;
    }

    /**
     * @param array $tplOptions
     */
    public function setTplOptions(array $tplOptions)
    {
        $this->tplOptions = $tplOptions;
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
        if(is_null($this->src)) {
            $this->src = $bundle->getPath();
        }

        $this->entity = $entity;
        $dirPath         = $this->src.'/Form/Type';
        $this->classPath = $dirPath.'/'.str_replace('\\', '/', $entity).'FormType.php';

        if (count($metadata->identifier) > 1) {
            throw new \RuntimeException('The form generator does not support entity classes with multiple primary keys.');
        }

        $fields           = $this->getFieldsFromMetadata($metadata);
        $maxColumnNameSize = 0;
        foreach($fields as $field) {
            $maxColumnNameSize = max($field['columnNameSize']+2, $maxColumnNameSize);
        }

        $options = array(
            'fields'               => $fields,
            'form_class'           => $entity . 'FormType',
            'form_label'           => $entity,
            'max_column_name_size' => $maxColumnNameSize,
        );

        $this->tplOptions = array_merge($this->tplOptions, $options);

        $this->generateForm();
        $this->generateServices();

        $g = new TranslationGenerator($this->filesystem, sprintf('%s/Resources/translations', $this->src), $entity, $fields);
        $g->generate();
    }

    /**
     * Generates the routing configuration.
     *
     */
    protected function generateForm()
    {
        $this->renderFile($this->skeletonDir, 'FormType.php.twig', $this->classPath, $this->tplOptions);
    }

    /**
     * Generates the routing configuration.
     *
     */
    protected function generateServices()
    {
        $target = sprintf(
            '%s/Resources/config/services.xml',
            $this->src
        );

        if(!file_exists($target)) {
            $this->renderFile($this->skeletonDir.'/..', 'config/services.xml.twig', $target, $this->tplOptions);
        }

        $services = file_get_contents($target);

        $key = "entity.form.{$this->tplOptions['entity_name']}.type";
        if(!strpos($services, $key)) {
            $service = $this->render($this->skeletonDir.'/..', 'config/service.xml.twig',  $this->tplOptions);
            $services = str_replace('</services>', $service."\n    </services>", $services);
            file_put_contents($target, $services);
        }
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
        $fields = $this->tplOptions['fields'];
        foreach($fields as &$field) {

            if(in_array($field['type'], ['date', 'datetime', 'dateinterval', 'string_array', 'integer_array'])) {
                $field['formType'] = $field['type'];
            }
        }

        if (!$metadata->isIdentifierNatural()) {
            foreach($metadata->identifier as $id) {
                unset($fields[$id]);
            }
        }

        foreach ($metadata->associationMappings as $fieldName => $relation) {
            if ($relation['type'] !== ClassMetadataInfo::ONE_TO_MANY) {

                $label = preg_replace('/([A-Z])/', ' \1', $fieldName);

                $label = trim($label);
                $label =  strtolower($label);
                $label = ucfirst($label);

                $fields[$fieldName] = [

                    'fieldName'      => $fieldName,
                    'type'           => $relation['targetEntity'],
                    'columnName'     => $fieldName,
                    'length'         => null,
                    'nullable'       => '',
                    'label'          => $label,
                    'columnNameSize' => strlen($fieldName),
                    'formType'       => 'objectChoice'
                ];
            }
        }

        return $fields;
    }
}
