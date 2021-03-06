<?php

namespace DevGeneratorToolBundle\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Command\Command;
use DevGeneratorToolBundle\Generator\DoctrineCrudGenerator;
use DevGeneratorToolBundle\Generator\DoctrineFormGenerator;
use DevGeneratorToolBundle\Command\Helper\DialogHelper;

/**
 * Generates a CRUD for a Doctrine entity.
 */
class GenerateDoctrineCrudCommand extends GenerateDoctrineCommand
{
    private $generator;
    private $formGenerator;
    private $src;
    private $outputBundle;

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputOption('entity', '', InputOption::VALUE_REQUIRED, 'The entity class name to initialize (shortcut notation)'),
                new InputOption('route-prefix', '', InputOption::VALUE_REQUIRED, 'The route prefix'),
                new InputOption('with-write', '', InputOption::VALUE_NONE, 'Whether or not to generate create, new and delete actions'),
                new InputOption('overwrite', '', InputOption::VALUE_NONE, 'Do not stop the generation if crud controller already exist, thus overwriting all generated files'),
                new InputOption('src', '', InputOption::VALUE_REQUIRED, 'output dir'),
                new InputOption('output-bundle', '', InputOption::VALUE_REQUIRED, 'output bundle'),

            ))
            ->setDescription('Generates a CRUD based on a Doctrine entity')
            ->setHelp(<<<EOT
The <info>tool-dev:generate:crud</info> command generates a CRUD based on a Doctrine entity.

The default command only generates the list and show actions.

<info>php app/console tool-dev:generate:crud --entity=AcmeBlogBundle:Post --route-prefix=post_admin</info>

Using the --with-write option allows to generate the new, edit and delete actions.

Using the --src option set output dir generated code exempale --src='./output' default output to ./AcmeBlogBundle

Using the --output-bundle' option set output output-bundle' generated code exempale --output-bundle='CoreBlogBundle' default output to AcmeBlogBundle

<info>php app/console tool-dev:generate:crud --entity=AcmeBlogBundle:Post --route-prefix=post_admin --with-write</info>
EOT
            )
            ->setName('tool-dev:generate:crud')
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('src')) {
            $this->src = $input->getOption('src');
        }

        if ($input->getOption('output-bundle')) {
            $this->outputBundle = $input->getOption('output-bundle');
        }

        $dialog = $this->getDialogHelper();

        if ($input->isInteractive()) {
            if (!$dialog->askConfirmation($output, $dialog->getQuestion('Do you confirm generation', 'yes', '?'), true)) {
                $output->writeln('<error>Command aborted</error>');

                return 1;
            }
        }

        $entity = Validators::validateEntityName($input->getOption('entity'));
        list($bundle, $entity) = $this->parseShortcutNotation($entity);
        $entityBundle = $bundle;

        $prefix = $this->getRoutePrefix($input, $entity);
        $withWrite = $input->getOption('with-write');
        $forceOverwrite = $input->getOption('overwrite');

        $dialog->writeSection($output, 'CRUD generation');

        $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($bundle).'\\'.$entity;
        $metadata    = $this->getEntityMetadata($entityClass);
        $bundle      = $this->getContainer()->get('kernel')->getBundle($bundle);

        $generator = $this->getGenerator();
        $generator->setEntityBundle($entityBundle);
        $generator->setSrc($this->src);
        $generator->setOutputBundle($this->outputBundle);

        $generator->generate($bundle, $entity, $metadata[0], $prefix, $withWrite, $forceOverwrite);

        $output->writeln('Generating the CRUD code: <info>OK</info>');

        $errors = array();

        // form
        if ($withWrite) {
            $this->generateForm($bundle, $entity, $metadata, $generator->getTplOptions());
            $output->writeln('Generating the Form code: <info>OK</info>');
        }

        $dialog->writeGeneratorSummary($output, $errors);
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $dialog = $this->getDialogHelper();
        $dialog->writeSection($output, 'Welcome to the Doctrine2 CRUD generator');

        // namespace
        $output->writeln(array(
            '',
            'This command helps you generate CRUD controllers and templates.',
            '',
            'First, you need to give the entity for which you want to generate a CRUD.',
            'You can give an entity that does not exist yet and the wizard will help',
            'you defining it.',
            '',
            'You must use the shortcut notation like <comment>AcmeBlogBundle:Post</comment>.',
            '',
        ));

        $entity = $dialog->askAndValidate($output, $dialog->getQuestion('The Entity shortcut name', $input->getOption('entity')), array('DevGeneratorToolBundle\Command\Validators', 'validateEntityName'), false, $input->getOption('entity'));
        $input->setOption('entity', $entity);
        list($bundle, $entity) = $this->parseShortcutNotation($entity);

        // Entity exists?
        $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($bundle).'\\'.$entity;
        $metadata = $this->getEntityMetadata($entityClass);

        // write?
        $withWrite = $input->getOption('with-write') ?: false;
        $output->writeln(array(
            '',
            'By default, the generator creates two actions: list and show.',
            'You can also ask it to generate "write" actions: new, update, and delete.',
            '',
        ));
        $withWrite = $dialog->askConfirmation($output, $dialog->getQuestion('Do you want to generate the "write" actions', $withWrite ? 'yes' : 'no', '?'), $withWrite);
        $input->setOption('with-write', $withWrite);

        // format
        $format = 'xml';
        // route prefix
        $prefix = $this->getRoutePrefix($input, $entity);
        $output->writeln(array(
            '',
            'Determine the routes prefix (all the routes will be "mounted" under this',
            'prefix: /prefix/, /prefix/new, ...).',
            '',
        ));
        $prefix = $dialog->ask($output, $dialog->getQuestion('Routes prefix', '/'.$prefix), '/'.$prefix);
        $input->setOption('route-prefix', $prefix);

        // summary
        $output->writeln(array(
            '',
            $this->getHelper('formatter')->formatBlock('Summary before generation', 'bg=blue;fg=white', true),
            '',
            sprintf('You are going to generate a CRUD controller for "<info>%s:%s</info>"', $bundle, $entity),
            sprintf('using the "<info>%s</info>" format.', $format),
            '',
        ));
    }

    /**
     * Tries to generate forms if they don't exist yet and if we need write operations on entities.
     */
    protected function generateForm($bundle, $entity, $metadata, array $tplOptions = [])
    {
        try {
            $generator = $this->getFormGenerator();
            $generator->setSrc($this->src);
            $generator->setTplOptions($tplOptions);
            $generator->generate($bundle, $entity, $metadata[0]);
        } catch (\RuntimeException $e) {
            // form already exists
        }
    }

    protected function getMainRoutePrefix(InputInterface $input, $entity)
    {
        $routeName = str_replace('\\', '_', $entity);
        $routeName = preg_replace('/(.)([A-Z])/', '\1-\2', $routeName);
        $prefix = strtolower($routeName);

        if ($prefix && '/' === $prefix[0]) {
            $prefix = substr($prefix, 1);
        }

        return $prefix;
    }

    protected function getRoutePrefix(InputInterface $input, $entity)
    {
        $prefix = $input->getOption('route-prefix') ?: strtolower(str_replace(array('\\', '/'), '_', $entity));

        if ($prefix && '/' === $prefix[0]) {
            $prefix = substr($prefix, 1);
        }

        return $prefix;
    }

    protected function getGenerator()
    {
        if (null === $this->generator) {
            $container = $this->getContainer();
            $dirSkeleton = $container->getParameter('dev_generator_tool.dir_skeleton');
            $this->generator = new DoctrineCrudGenerator($container->get('filesystem'), $dirSkeleton.'/crud');
            $this->generator->setContainer($this->getContainer());
        }

        return $this->generator;
    }

    public function setGenerator(DoctrineCrudGenerator $generator)
    {
        $this->generator = $generator;
        $this->generator->setContainer($this->getContainer());
    }

    protected function getFormGenerator()
    {
        if (null === $this->formGenerator) {
            $container = $this->getContainer();
            $dirSkeleton = $container->getParameter('dev_generator_tool.dir_skeleton');
            $this->formGenerator = new DoctrineFormGenerator($container->get('filesystem'), $dirSkeleton.'/form');
            $this->formGenerator->setContainer($container);
        }

        return $this->formGenerator;
    }

    public function setFormGenerator(DoctrineFormGenerator $formGenerator)
    {
        $this->formGenerator = $formGenerator;
    }

    protected function getDialogHelper()
    {
        $dialog = $this->getHelperSet()->get('dialog');
        if (!$dialog || get_class($dialog) !== 'DevGeneratorToolBundle\Command\Helper\DialogHelper') {
            $this->getHelperSet()->set($dialog = new DialogHelper());
        }

        return $dialog;
    }
}
