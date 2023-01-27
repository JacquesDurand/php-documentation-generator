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

namespace ApiPlatform\PDGBundle\Twig;

use ApiPlatform\PDGBundle\Services\ConfigurationHandler;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MarkdownExtension extends AbstractExtension implements TemplateExtension
{
    public function __construct(private readonly ConfigurationHandler $configuration)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('md_link', [$this, 'getLink']),
            new TwigFilter('md_value', [$this, 'formatValue']),
        ];
    }

    public function getLink(\ReflectionClass $class): string
    {
        $name = $class->getName();

        // Internal
        if (str_starts_with($name, $this->configuration->get('reference.src').'\\')) {
            return sprintf('[%s](/reference/%s)', $name, str_replace(['ApiPlatform\\', '\\'], ['', '/'], $name));
        }

        // Symfony
        if (str_starts_with($name, 'Symfony')) {
            return sprintf('[%s](https://symfony.com/doc/current/index.html)', $name);
        }

        // PHP
        if (!$class->isUserDefined()) {
            return sprintf('[%s](https://php.net/class.%s)', $name, strtolower($name));
        }

        return $name;
    }

    public function formatValue($value): string
    {
        if (!\is_array($value)) {
            return $value;
        }

        return \PHP_EOL.'```php'.\PHP_EOL.print_r($value, true).'```'.\PHP_EOL;
    }
}