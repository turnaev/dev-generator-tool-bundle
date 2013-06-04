<?php

namespace DevGeneratorToolBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Command\GenerateEntitiesDoctrineCommand as GenerateEntitiesDoctrineCommandParent;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\Tools\EntityRepositoryGenerator;
use Doctrine\Bundle\DoctrineBundle\Mapping\DisconnectedMetadataFactory;

/**
 * Generate entity classes from mapping information
 *
 */
class GenerateEntitiesDoctrineCommand extends GenerateEntitiesDoctrineCommandParent
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();
        $this->setName('tool-dev:generate:generate:entities');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $manager = new DisconnectedMetadataFactory($this->getContainer()->get('doctrine'));

        try {
            $bundle = $this->getApplication()->getKernel()->getBundle($input->getArgument('name'));

            $output->writeln(sprintf('Generating entities for bundle "<info>%s</info>"', $bundle->getName()));
            $metadata = $manager->getBundleMetadata($bundle);
        } catch (\InvalidArgumentException $e) {
            $name = strtr($input->getArgument('name'), '/', '\\');

            if (false !== $pos = strpos($name, ':')) {
                $name = $this->getContainer()->get('doctrine')->getEntityNamespace(substr($name, 0, $pos)).'\\'.substr($name, $pos + 1);
            }

            if (class_exists($name)) {
                $output->writeln(sprintf('Generating entity "<info>%s</info>"', $name));
                $metadata = $manager->getClassMetadata($name, $input->getOption('path'));
            } else {
                $output->writeln(sprintf('Generating entities for namespace "<info>%s</info>"', $name));
                $metadata = $manager->getNamespaceMetadata($name, $input->getOption('path'));
            }
        }

        $generator = $this->getEntityGenerator();

        $backupExisting = !$input->getOption('no-backup');
        $generator->setBackupExisting($backupExisting);

        $repoGenerator = new \DevGeneratorToolBundle\Generator\EntityRepositoryGenerator();
        foreach ($metadata->getMetadata() as $m) {

            if ($backupExisting) {
                $basename = substr($m->name, strrpos($m->name, '\\') + 1);
                $output->writeln(sprintf('  > backing up <comment>%s.php</comment> to <comment>%s.php~</comment>', $basename, $basename));
            }
            // Getting the metadata for the entity class once more to get the correct path if the namespace has multiple occurrences
            try {
                $entityMetadata = $manager->getClassMetadata($m->getName(), $input->getOption('path'));
            } catch (\RuntimeException $e) {
                // fall back to the bundle metadata when no entity class could be found
                $entityMetadata = $metadata;
            }

            $output->writeln(sprintf('  > generating <comment>%s</comment>', $m->name));
            $generator->generate(array($m), $entityMetadata->getPath());

            if ($m->customRepositoryClassName && false !== strpos($m->customRepositoryClassName, $metadata->getNamespace())) {
                $repoGenerator->writeEntityRepositoryClass($m->customRepositoryClassName, $metadata->getPath());
            } else {

                $path = $metadata->getPath() . '/' . str_replace('\\', DIRECTORY_SEPARATOR, $m->name);
                $dir = dirname($path) . DIRECTORY_SEPARATOR . 'Repository';

                $repoName = '/'.basename($path).'Repository';

                $repoGenerator->writeEntityRepositoryClass($repoName, $dir, true);
            }
        }
    }
}
