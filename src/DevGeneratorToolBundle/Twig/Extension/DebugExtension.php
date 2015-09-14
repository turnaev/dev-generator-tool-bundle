<?php

namespace DevGeneratorToolBundle\Twig\Extension;

class DebugExtension extends \Twig_Extension
{
    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            'fb' => new \Twig_Function_Method($this, 'fb'),
            'w' => new \Twig_Function_Method($this, 'w'),
        ];
    }

    /**
     * debug to console.
     *
     * @param string $string
     *
     * @return string
     */
    public function fb($var)
    {
        fb3($var);

        return;
    }

    /**
     * @param mixed $string
     *
     * @return string
     */
    public function w($var)
    {
        w($var);

        return;
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'twig_debug';
    }
}
