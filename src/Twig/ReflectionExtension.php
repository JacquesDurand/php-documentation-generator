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

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ReflectionExtension extends AbstractExtension
{
    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_PROTECTED = 'protected';
    public const VISIBILITY_PUBLIC = 'public';

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getVisibility')
        ];
    }

    public function getVisibility(\ReflectionClass|\ReflectionClassConstant|\ReflectionMethod|\ReflectionProperty $reflection): string
    {
        if ($reflection->isPrivate()) {
            return self::VISIBILITY_PRIVATE;
        }

        if ($reflection->isProtected()) {
            return self::VISIBILITY_PROTECTED;
        }

        return self::VISIBILITY_PUBLIC;
    }
}