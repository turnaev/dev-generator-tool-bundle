<?php


namespace DevGeneratorToolBundle\Manipulator;

use Symfony\Component\DependencyInjection\Container;

/**
 * Changes the PHP code of a YAML routing file.
 *
 */
class RoutingManipulator extends Manipulator
{
    private $file;

    /**
     * Constructor.
     *
     * @param string $file The YAML routing file path
     */
    public function __construct($file)
    {
        $this->file = $file;
    }

    /**
     * Adds a routing resource at the top of the existing ones.
     *
     * @param string $bundle
     * @param string $format
     * @param string $prefix
     * @param string $path
     *
     * @return Boolean true if it worked, false otherwise
     *
     * @throws \RuntimeException If bundle is already imported
     */
    public function addResource($bundle, $format, $prefix = '/', $path = 'routing')
    {
w($this->file);
        $current = '';
        if (file_exists($this->file)) {
            $current = file_get_contents($this->file);

            // Don't add same bundle twice
            if (false !== strpos($current, '@'.$bundle)) {
                throw new \RuntimeException(sprintf('Bundle "%s" is already imported.', $bundle));
            }

        } elseif (!is_dir($dir = dirname($this->file))) {
            mkdir($dir, 0777, true);
        }

        $code = null;

        $resource = sprintf("@%s/Resources/config/%s.%s", $bundle, $path, $format);

        if($format = 'xml') {

            if($current) {
                $code = $this->addedRoutingToXml($current, $resource, $prefix);
            }

        } else {
            $code = sprintf("%s:\n", Container::underscore(substr($bundle, 0, -6)).('/' !== $prefix ? '_'.str_replace('/', '_', substr($prefix, 1)) : ''));
            if ('annotation' == $format) {
                $code .= sprintf("    resource: \"@%s/Controller/\"\n    type:     annotation\n", $bundle);
            } else {
                $code .= sprintf("    resource: \"%s\"\n", $resource);
            }
            $code .= sprintf("    prefix:   %s\n", $prefix);
            $code .= "\n";
            $code .= $current;
        }

        if ($code && (false === file_put_contents($this->file, $code))) {
            return false;
        }

        return true;
    }

    private function addedRoutingToXml($xml, $resource, $prefix)
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml);

        $element = $dom->createElement('import');
        $element->setAttribute('resource', $resource);
        $element->setAttribute('prefix', $prefix);

        // Вставляем новый элемент как корень (потомок документа)
        $root = $dom->documentElement;

        $root->appendChild($element);

        return $dom->saveXML();
    }
}
