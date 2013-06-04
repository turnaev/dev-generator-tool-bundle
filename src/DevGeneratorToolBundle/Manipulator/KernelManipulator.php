<?php


namespace DevGeneratorToolBundle\Manipulator;

use Sensio\Bundle\FrameworkExtraBundle\Tests\EventListener\Fixture\FooControllerCacheAtMethod;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Changes the PHP code of a Kernel.
 *
 */
class KernelManipulator extends Manipulator
{
    private $kernel;
    private $reflected;

    /**
     * Constructor.
     *
     * @param KernelInterface $kernel A KernelInterface instance
     */
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
        $this->reflected = new \ReflectionObject($kernel);
    }

    /**
     * Adds a bundle at the end of the existing ones.
     *
     * @param string $bundle The bundle class name
     *
     * @return Boolean true if it worked, false otherwise
     *
     * @throws \RuntimeException If bundle is already defined
     */
    public function addBundle($bundle)
    {
        if (!$this->reflected->getFilename()) {
            return false;
        }

        $src = file($this->reflected->getFilename());
        $method = $this->reflected->getMethod('registerBundles');
        $lines = array_slice($src, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1);

        // Don't add same bundle twice
        if (false !== strpos(implode('', $lines), $bundle)) {
            throw new \RuntimeException(sprintf('Bundle "%s" is already defined in "AppKernel::registerBundles()".', $bundle));
        }


        $this->setCode(token_get_all('<?php '.implode('', $lines)), $method->getStartLine());
        while ($token = $this->next()) {

            // $bundles
            if (T_VARIABLE !== $token[0] || '$bundles' !== $token[1]) {
                continue;
            }
            // =
            $this->next();

            // array || [
            $token = $this->next();

            if ('[' != $token || (!isset($token[0]) && T_ARRAY !== $token[0])) {
                return false;
            }

            // add the bundle at the end of the array
            while ($token = $this->next()) {
                // look for )];
                if (!in_array($this->value($token),[']', ')'])) {
                    continue;
                }

                if (';' !== $this->value($this->peek())) {
                    continue;
                }

                // ;
                $this->next();

                for($i=2;$i<1000; $i++) {

                    $line = trim($src[$this->line - $i]);

                    if($line) {

                        $line = rtrim($src[$this->line - $i]);

                        if(!preg_match('/,$/', $line)) {

                            $src[$this->line - $i] = $line.',';
                        }
                        break;
                    }
                }

                $subSrc =  array(rtrim(rtrim($src[$this->line - 2]), ',') . "\n");

                $lines = array_merge(
                    array_slice($src, 0, $this->line - 2),
                    // Appends a separator comma to the current last position of the array
                    $subSrc,
                    array(sprintf("            new %s(),\n\n", $bundle)),
                    array_slice($src, $this->line - 1)
                );

                file_put_contents($this->reflected->getFilename(), implode('', $lines));

                return true;
            }
        }
    }
}
