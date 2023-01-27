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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ConfigurationCommand extends Command
{
    private \ReflectionClass $reflectionClass;

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
            ->setDescription('Creates a reference documentation for a bundle configuration')
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
                default: sprintf('%s/reference.mdx.twig', $this->templateDir)
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $filename = $input->getArgument('filename');

        $style->info(sprintf('Generating reference for "%s"', $filename));
        $namespace = $this->getNamespace($filename);

        $this->reflectionClass = new \ReflectionClass($namespace);
        $outputFile = $input->getArgument('output');

        if ($this->reflectionClass->implementsInterface(ConfigurationInterface::class)) {
            return $this->generateConfigExample($style, $outputFile);
        }

        $content = '';
        $content = $this->outputFormatter->writePageTitle($this->reflectionClass, $content);
        $content = $this->outputFormatter->writeClassName($this->reflectionClass, $content);
        $content = $this->reflectionHelper->handleParent($this->reflectionClass, $content);
        $content = $this->reflectionHelper->handleImplementations($this->reflectionClass, $content);
        $content = $this->phpDocHelper->handleClassDoc($this->reflectionClass, $content);
        $content = $this->reflectionHelper->handleClassConstants($this->reflectionClass, $content);
        $content = $this->reflectionHelper->handleProperties($this->reflectionClass, $content);
        $content = $this->reflectionHelper->handleMethods($this->reflectionClass, $content);

        if (!$outputFile) {
            fwrite(\STDOUT, $content);
            $style->success('Reference successfully printed on stdout for '.$filename);

            return Command::SUCCESS;
        }

        if (!fwrite(fopen($outputFile, 'w'), $content)) {
            $style->error('Error opening or writing '.$outputFile);

            return Command::FAILURE;
        }

        $style->success('Reference successfully generated for '.$filename);

        return Command::SUCCESS;
    }

    private function generateConfigExample(SymfonyStyle $style, ?string $outputFile): int
    {
        $style->info('Generating configuration reference');

        $yaml = (new YamlReferenceDumper())->dump($this->reflectionClass->newInstance());
        if (!$yaml) {
            $style->error('No configuration is available');

            return Command::FAILURE;
        }

        $content = $this->outputFormatter->writePageTitle($this->reflectionClass, '');
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

    private function getNamespace(string $filename): string
    {
        // Remove root dir from file path
        $filename = preg_replace(sprintf('#^%s/#i', $this->configuration->get('reference.src')), '', $filename);

        // Remove file extension
        $filename = str_replace(['/', '.php'], ['\\', ''], $filename);

        // Guess namespace
        return sprintf('%s\\%s', $this->configuration->get('reference.namespace'), $filename);
    }
}
