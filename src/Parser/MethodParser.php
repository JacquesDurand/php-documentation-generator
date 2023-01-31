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

namespace ApiPlatform\PDGBundle\Parser;

use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ThrowsTagValueNode;

final class MethodParser extends AbstractParser
{
    public function __construct(private readonly \ReflectionMethod $reflection)
    {
    }

    public function getModifier(): string
    {
        return implode(' ', \Reflection::getModifierNames($this->getReflection()->getModifiers()));
    }

    /**
     * @return ParameterParser[]
     */
    public function getParameters(): array
    {
        return array_map(
            fn (\ReflectionParameter $parameter) => new ParameterParser($parameter),
            $this->getReflection()->getParameters()
        );
    }

    public function getReturnType(): ?TypeParser
    {
        return $this->getReflection()->hasReturnType() ? new TypeParser($this->getReflection()->getReturnType()) : null;
    }

    public function getDocComment(): string|false
    {
        // method has no doc
        if (!$docComment = parent::getDocComment()) {
            return $docComment;
        }

        // import and replace "inheritdoc"
        if (str_contains($docComment, '@inheritdoc')) {
            $name = $this->getReflection()->getName();
            $class = new ClassParser($this->getReflection()->getDeclaringClass());

            // import docComment from parent class first
            if (
                false !== ($parentClass = $class->getParentClass())
                && $parentClass->hasMethod($name)
                && ($parentDocComment = $parentClass->getMethod($name)->getDocComment())
            ) {
                return preg_replace('/{?@inheritdoc}?/', preg_replace('/(?:\/\*\*(?:\n *\*)? )|(\n? *\*\/)/', '', $parentDocComment), $docComment);
            }

            // import docComment from interfaces
            foreach ($class->getInterfaces() as $interface) {
                if (
                    $interface->hasMethod($name)
                    && ($interfaceDocComment = $interface->getMethod($name)->getDocComment())
                ) {
                    return preg_replace('/{?@inheritdoc}?/', preg_replace('/(?:\/\*\*(?:\n *\*)? )|(\n? *\*\/)/', '', $interfaceDocComment), $docComment);
                }
            }
        }

        return $docComment;
    }

    /**
     * @return ReturnTagValueNode[]
     */
    public function getAdditionalReturnTypes(): array
    {
        // retrieve additional return types from doc
        return $this->getPhpDoc()->getReturnTagValues();
    }

    /**
     * @return ThrowsTagValueNode[]
     */
    public function getThrowTags(): array
    {
        return $this->getPhpDoc()->getThrowsTagValues();
    }

    public function getReflection(): \ReflectionMethod
    {
        return $this->reflection;
    }
}
