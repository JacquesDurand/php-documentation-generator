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

namespace ApiPlatform\PDGBundle\Twig\Helper;

abstract class AbstractHelper implements HelperInterface
{
    public function getModifier(\ReflectionMethod|\ReflectionProperty $reflection): string
    {
        return implode(' ', \Reflection::getModifierNames($reflection->getModifiers()));
    }

    public function inheritDoc(\ReflectionMethod|\ReflectionClass $reflection): string
    {
        $doc = $reflection->getDocComment();
        if (!str_contains($doc, '{@inheritdoc}')) {
            return $doc;
        }

        // Imo Trait method should not have @inheritdoc as they might not "inherit" depending
        // on the using class
        if ($reflection instanceof \ReflectionClass && $reflection->isTrait()) {
            return $doc;
        }

        if ($reflection instanceof \ReflectionMethod) {
            if ($reflection->getPrototype()->isUserDefined()) {
                return $doc;
            }

//            $reflection->getPrototype()->getDocComment()
        }

        return $doc;
    }

    protected function getParameterName(\ReflectionParameter $parameter): string
    {
        return $parameter->isPassedByReference() ? '&$'.$parameter->getName() : '$'.$parameter->getName();
    }
}
