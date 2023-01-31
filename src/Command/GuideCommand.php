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

use ApiPlatform\PDGBundle\Services\Reference\OutputFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class GuideCommand extends Command
{
    // Regular expression to match comment
    private const REGEX = '/^\s*\/\/\s/';

    public function __construct(private readonly OutputFormatter $outputFormatter)
    {
        parent::__construct(name: 'guide');
    }

    protected function configure(): void
    {
        $this
            ->setHelp(help: 'Creates a markdown guide based on a PHP code.')
            ->setDescription(description: 'Creates a markdown guide based on a PHP code.')
            ->addArgument(name: 'file', mode: InputArgument::REQUIRED, description: 'PHP file to make the guide of.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $stderr = $io->getErrorStyle();
        $file = $input->getArgument('file');
        $handle = fopen($file, 'r');

        if (!$handle) {
            $stderr->info(sprintf('Error opening %s.', $file));

            return self::INVALID;
        }

        // Let's split the code between an array of code and an array of text
        $sections = [];
        $linesOfCode = $linesOfText = $currentSection = 0;
        $sections[$currentSection] = ['text' => [], 'code' => []];
        // splits namespaces if found
        $namespaceOpen = false;
        // We need to put front matter headers at the start of the markdown file
        $frontMatterOpen = false;
        // we keep the headers as-is in this array
        $header = [];

        $stderr->info(sprintf('Creating guide %s.', $file));

        while (($line = fgets($handle)) !== false) {
            if (!isset($sections[$currentSection]['text'])) {
                $sections[$currentSection] = ['text' => [], 'code' => []];
            }

            if (!trim($line)) {
                continue;
            }

            // This is a line of text
            if (preg_match(self::REGEX, $line)) {
                $text = preg_replace(self::REGEX, '', $line);
                if ('---' === trim($text)) {
                    $frontMatterOpen = !$frontMatterOpen;
                    $header[] = $text;
                    continue;
                }

                if ($frontMatterOpen) {
                    $header[] = $text;
                    continue;
                }

                if (!trim($text)) {
                    continue;
                }

                if ($linesOfCode && ($linesOfText || $currentSection > 0)) {
                    ++$currentSection;
                    $sections[$currentSection] = ['text' => [], 'code' => []];
                    $linesOfCode = $linesOfText = 0;
                }

                $sections[$currentSection]['text'][] = $text;
                ++$linesOfText;

                continue;
            }

            if (false !== preg_match('/namespace (.+) \{$/', $line, $matches) && $matches) {
                $line = str_replace(' {', ';', $line);
                $namespaceOpen = true;
            } elseif ($namespaceOpen) {
                if ($line === '}'.\PHP_EOL) {
                    $line = \PHP_EOL;
                    $namespaceOpen = false;
                } else {
                    $line = substr($line, 4);
                }
            }

            if ($matches) {
                $sections[$currentSection]['code'][] = '// src/'.str_replace('\\', '/', $matches[1]).'.php'.\PHP_EOL;
            }

            $sections[$currentSection]['code'][] = $line;
            ++$linesOfCode;

            if ($linesOfText && $linesOfCode >= $linesOfText) {
                ++$currentSection;
                $linesOfCode = $linesOfText = 0;
            }
        }

        fclose($handle);

        $a = implode('', $header);
        $a .= <<<MD
<div className="sections">

MD;

        foreach ($sections as $i => $section) {
            $a .= <<<MD
  <div className="section" id="section-$i">
    <div className="annotation">
    <a className="anchor" href="#section-$i">&#x00a7;</a>

MD;
            $text = implode('', $section['text'] ?: [\PHP_EOL]);
            $a .= str_contains($text, '[codeSelector]')
                ? $this->outputFormatter->formatCodeSelector($text)
                : $text;
            $a .= <<<MD
    </div>
    <div className="content">

```php

MD;
            $a .= implode('', $section['code'] ?: [\PHP_EOL]);
            $a .= <<<MD
```
    </div>
  </div>

MD;
        }

        $a .= '</div>';

        $output->write($a);

        return self::SUCCESS;
    }
}
