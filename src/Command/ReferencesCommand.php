<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\PDGBundle\Command;

use ApiPlatform\PDGBundle\Parser\ClassParser;
use ApiPlatform\PDGBundle\Services\ConfigurationHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Twig\Environment;

final class ReferencesCommand extends Command
{
    use CommandTrait;

    public function __construct(
        private readonly ConfigurationHandler $configuration,
        private readonly Environment $environment,
        private readonly string $templatePath
    ) {
        parent::__construct(name: 'references');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Creates references documentation for PHP classes')
            ->addOption(
                name: 'template-path',
                mode: InputOption::VALUE_REQUIRED,
                description: 'The path to the template files to use to generate each reference output file',
                default: $this->templatePath
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $patterns = $this->configuration->get('reference.patterns');
        $tagsToIgnore = $patterns['class_tags_to_ignore'] ?? ['@internal', '@experimental'];
        $files = $this->findFiles($patterns['directories'] ?? [], $patterns['names'] ?? ['*.php'], $patterns['exclude'] ?? []);
        $namespaces = [];

        // get the output extension
        $extension = pathinfo($this->getTemplateFile($input->getOption('template-path'), 'reference.*.twig')->getBasename('.twig'), \PATHINFO_EXTENSION);

        $style = new SymfonyStyle($input, $output);
        $style->progressStart(\count($files));

        foreach ($files as $file) {
            $relativeToSrc = Path::makeRelative($file->getPath(), $this->configuration->get('reference.src'));

            if (!@mkdir($concurrentDirectory = $this->configuration->get('target.directories.reference_path').\DIRECTORY_SEPARATOR.$relativeToSrc, 0777, true) && !is_dir($concurrentDirectory)) {
                $style->error(sprintf('Cannot create directory "%s".', $concurrentDirectory));

                return self::FAILURE;
            }

            $namespace = rtrim(sprintf('%s\\%s', $this->configuration->get('reference.namespace'), str_replace([\DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relativeToSrc)), '\\');

            try {
                $reflectionClass = new ClassParser(new \ReflectionClass(sprintf('%s\\%s', $namespace, $file->getBasename('.php'))));
            } catch (\ReflectionException) {
                $style->error(sprintf('File "%s" does not seem to be a valid PHP class.', $file->getPathname()));

                return self::FAILURE;
            }

            foreach ($tagsToIgnore as $tagToIgnore) {
                if ($reflectionClass->hasTag($tagToIgnore)) {
                    continue 2;
                }
            }

            // class is not an interface nor a trait, and has no protected/public methods nor properties
            if (
                !$reflectionClass->isTrait()
                && !$reflectionClass->isInterface()
                && !\count($reflectionClass->getMethods())
                && !\count($reflectionClass->getProperties())
            ) {
                continue;
            }

            // do not display output on sub-command
            $verbosity = $output->getVerbosity();
            $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);

            // run "reference" command
            if (
                self::FAILURE === $this->getApplication()?->find('reference')->run(new ArrayInput([
                    'filename' => $file->getPathName(),
                    // todo remove output as it's already in the configuration file?
                    'output' => sprintf('%s%s%s%2$s%s.%s', $this->configuration->get('target.directories.reference_path'), \DIRECTORY_SEPARATOR, $relativeToSrc, $file->getBaseName('.php'), $extension),
                    '--template-path' => $input->getOption('template-path'),
                ]), $output)
            ) {
                $style->error(sprintf('Failed generating reference "%s".', $file->getPathname()));

                return self::FAILURE;
            }

            // restore default verbosity
            $output->setVerbosity($verbosity);

            $namespaces[$namespace][] = $reflectionClass;

            $style->progressAdvance();
        }

        $style->progressFinish();

        // Creating an index like https://angular.io/api
        $templateFile = $this->getTemplateFile($input->getOption('template-path'), 'index.*.twig');
        $content = $this->environment->render($templateFile->getFilename(), ['namespaces' => $namespaces]);
        $fileName = sprintf(
            '%s%sindex.%s',
            $this->configuration->get('target.directories.reference_path'),
            \DIRECTORY_SEPARATOR,
            pathinfo($templateFile->getBasename('.twig'), \PATHINFO_EXTENSION)
        );
        $dirName = pathinfo($fileName, \PATHINFO_DIRNAME);
        if (!is_dir($dirName)) {
            mkdir($dirName, 0777, true);
        }
        if (!file_put_contents($fileName, $content)) {
            $style->error(sprintf('Cannot write file "%s".', $input->getArgument('output')));

            return self::FAILURE;
        }

        $style->success('References index successfully generated.');

        return self::SUCCESS;
    }

    private function findFiles(array $directories, array $names, array $exclude): Finder
    {
        return (new Finder())->files()
            ->in(array_map(fn (string $directory) => $this->configuration->get('reference.src').\DIRECTORY_SEPARATOR.$directory, $directories))
            ->name($names)
            ->notName($exclude);
    }
}
