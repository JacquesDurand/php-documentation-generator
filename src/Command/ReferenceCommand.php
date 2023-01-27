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

use ApiPlatform\PDGBundle\Services\ConfigurationHandler;
use ApiPlatform\PDGBundle\Services\Reference\OutputFormatter;
use ApiPlatform\PDGBundle\Services\Reference\PhpDocHelper;
use ApiPlatform\PDGBundle\Services\Reference\Reflection\ReflectionHelper;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ReferenceCommand extends Command
{
    public function __construct(
        private readonly PhpDocHelper $phpDocHelper,
        private readonly ReflectionHelper $reflectionHelper,
        private readonly OutputFormatter $outputFormatter,
        private readonly ConfigurationHandler $configuration,
        private readonly string $templateDir
    ) {
        parent::__construct(name: 'reference');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Creates a reference documentation for a PHP class')
            ->addArgument(name: 'filename', mode: InputArgument::REQUIRED)
            ->addArgument(
                name: 'output',
                mode: InputArgument::OPTIONAL,
                description: 'The path to the file where the reference will be printed. Leave empty for screen printing'
            )
            ->addOption(
                name: 'template',
                mode: InputOption::VALUE_REQUIRED,
                description: 'The path to the template file to use to generate the output file',
                default: sprintf('%s/mdx/reference.mdx.twig', $this->templateDir)
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = new \SplFileInfo($input->getArgument('filename'));
        $reflectionClass = new \ReflectionClass($this->getNamespace($file));

        $style = new SymfonyStyle($input, $output);
        $style->info(sprintf('Generating reference for "%s"', $file->getPathname()));

        if ($reflectionClass->implementsInterface(ConfigurationInterface::class)) {
            return $this->getApplication()?->find('configuration')->run(new ArrayInput([
                'filename' => $file->getPathName(),
                'output' => $input->getArgument('output'),
            ]), $output);
        }

        $content = '';
        $content = $this->outputFormatter->writePageTitle($reflectionClass, $content);
        $content = $this->outputFormatter->writeClassName($reflectionClass, $content);
        $content = $this->reflectionHelper->handleParent($reflectionClass, $content);
        $content = $this->reflectionHelper->handleImplementations($reflectionClass, $content);
        $content = $this->phpDocHelper->handleClassDoc($reflectionClass, $content);
        $content = $this->reflectionHelper->handleClassConstants($reflectionClass, $content);
        $content = $this->reflectionHelper->handleProperties($reflectionClass, $content);
        $content = $this->reflectionHelper->handleMethods($reflectionClass, $content);

        if (!$input->getArgument('output')) {
            fwrite(\STDOUT, $content);
            $style->success('Reference successfully printed on stdout for '.$file->getPathname());

            return Command::SUCCESS;
        }

        if (!fwrite(fopen($input->getArgument('output'), 'w'), $content)) {
            $style->error('Error opening or writing '.$input->getArgument('output'));

            return Command::FAILURE;
        }

        $style->success('Reference successfully generated for '.$file->getPathname());

        return Command::SUCCESS;
    }

    private function generateConfigExample(\ReflectionClass $reflectionClass, SymfonyStyle $style, ?string $outputFile): int
    {
        $style->info('Generating configuration reference');

        $yaml = (new YamlReferenceDumper())->dump($reflectionClass->newInstance());
        if (!$yaml) {
            $style->error('No configuration is available');

            return Command::FAILURE;
        }

        $content = $this->outputFormatter->writePageTitle($reflectionClass, '');
        $content .= '# Configuration Reference'.\PHP_EOL;
        $content .= sprintf('```yaml'.\PHP_EOL.'%s```', $yaml);
        $content .= \PHP_EOL;

        if (!fwrite(fopen($outputFile, 'w'), $content)) {
            $style->error('Error opening or writing '.$outputFile);

            return Command::FAILURE;
        }
        $style->success('Configuration reference successfully generated');

        return Command::SUCCESS;
    }

    private function getNamespace(\SplFileInfo $file): string
    {
        // Remove root path from file path
        $namespace = preg_replace(sprintf('#^%s/#i', $this->configuration->get('reference.src')), '', $file->getPath());
        // Convert it to namespace format
        $namespace = str_replace('/', '\\', $namespace);
        // Prepend main namespace
        $namespace = sprintf('%s\\%s', $this->configuration->get('reference.namespace'), $namespace);

        return sprintf('%s\\%s', $namespace, $file->getBasename('.'.$file->getExtension()));
    }
}
