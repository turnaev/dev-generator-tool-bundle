<?php

namespace DevGeneratorToolBundle\Generator;

use Symfony\Component\Filesystem\Filesystem;

class TranslationGenerator
{
    /**
     * @var Filesystem
     */
    protected $filesystem;
    /**
     * @var string
     */
    protected $dir;
    /**
     * @var string
     */
    protected $entity;
    /**
     * @var array
     */
    protected $fieldMappings;

    /**
     * @param Filesystem $filesystem
     * @param string     $dir
     * @param string     $entity
     * @param array      $fieldMappings
     */
    public function __construct(Filesystem $filesystem, $dir, $entity, array $fieldMappings = [])
    {
        $this->filesystem    = $filesystem;
        $this->dir           = $dir;
        $this->entity        = $entity;
        $this->fieldMappings = $fieldMappings;
    }

    public function generate()
    {
        if (!file_exists($this->dir)) {
            $this->filesystem->mkdir($this->dir, 0777);
        }

        $trans = [];

        $trans[$this->entity] = $this->entity;
        foreach ($this->fieldMappings as $fieldMapping) {
            $trans[$fieldMapping['fieldName']] = $fieldMapping['label'];
        }

        $gt = new \DevGeneratorToolBundle\Translater\GoogleTranslater();

        foreach (['ru', 'en'] as $locale) {
            $file = sprintf('%s/%s.%s.yml', $this->dir, $this->entity, $locale);

            if (!file_exists($file)) {
                file_put_contents($file, "#Localization file for the entity {$this->entity}. Locale {$locale}.\n");
            }
            $comments = array_filter(file($file), function ($str) {
                return preg_match('/^#/', $str);
            });
            $comments = implode("\n", $comments);

            if ($locale == 'ru') {
                $translationsArr = \Symfony\Component\Yaml\Yaml::parse($file);

                $translationsArr = $translationsArr ? $translationsArr : [];

                foreach ($trans as $key => $tran) {
                    if (!isset($translationsArr[$key])) {
                        $gtTran = $gt->translateText($tran, $fromLanguage = 'en', $toLanguage = 'ru');

                        if (!$gt->getErrors()) {
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
            if ($translationsArr) {
                $translationsYml = \Symfony\Component\Yaml\Yaml::dump($translationsArr);
            } else {
                $translationsYml = '';
            }

            file_put_contents($file, $comments.$translationsYml);
        }
    }
}
