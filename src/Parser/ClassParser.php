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

final class ClassParser extends AbstractParser
{
    public function __construct(private readonly \ReflectionClass $reflection)
    {
    }

    public function hasTag(string $searchedTag): bool
    {
        // class has no doc (only search for class doc without inheritance)
        if (!$this->getReflection()->getDocComment()) {
            return false;
        }

        foreach ($this->getPhpDoc()->getTags() as $tag) {
            if ($searchedTag === $tag->name) {
                return true;
            }
        }

        return false;
    }

    public function getParentClass(): self|false
    {
        if ($parentClass = $this->getReflection()->getParentClass()) {
            return new self($parentClass);
        }

        return false;
    }

    public function getInterfaces(): array
    {
        return array_map(fn (\ReflectionClass $class) => new self($class), $this->getReflection()->getInterfaces());
    }

    public function getDocComment(): string|false
    {
        // class has no doc
        if (!$docComment = parent::getDocComment()) {
            return $docComment;
        }

        // import and replace "inheritdoc" (except for traits)
        if (!$this->getReflection()->isTrait() && str_contains($docComment, '@inheritdoc')) {
            // import docComment from parent class, not from interfaces
            if (false !== ($parentClass = $this->getParentClass()) && '' !== ($parentDocComment = $parentClass->getDocComment())) {
                $docComment = preg_replace('/{?@inheritdoc}?/', $parentDocComment, $docComment);
            }
        }

        // todo check "@see" tags to import absolute namespace if available

        return $docComment;
    }

    public function getCodeSelector(): array
    {
        $codeSelector = [];
        $blocks = preg_split('/(\[codeSelector\][\s\S\w\n]*?\[\/codeSelector\])/', $this->getReflection()->getDocComment(), 0, \PREG_SPLIT_DELIM_CAPTURE);
        foreach ($blocks as $i => $block) {
            if (str_contains($block, 'codeSelector')) {
                if (false !== preg_match_all('/```(\w+)/', $block, $languages) && $languages) {
                    $codeSelector[$i] = [];
                    foreach ($languages[1] as $language) {
                        $codeSelector[$i][$language] = $block;
                    }
                }
            }
        }

        return array_values($codeSelector);
    }

    /**
     * Get public and protected constants.
     *
     * @return ConstantParser[]
     */
    public function getConstants(): array
    {
        return array_map(
            fn (\ReflectionClassConstant $constant) => new ConstantParser($constant),
            $this->getReflection()->getReflectionConstants(\ReflectionClassConstant::IS_PUBLIC | \ReflectionClassConstant::IS_PROTECTED)
        );
    }

    /**
     * Get public and protected properties, and private ones with accessors.
     *
     * @return PropertyParser[]
     */
    public function getProperties(): array
    {
        $properties = [];
        foreach ($this->getReflection()->getProperties() as $property) {
            $property = new PropertyParser($property);

            if (!$property->isPrivate() || $property->getAccessors()) {
                $properties[] = $property;
            }
        }

        return $properties;
    }

    /**
     * Get public and protected methods, except for constructor, external ones and accessors.
     *
     * @return MethodParser[]
     */
    public function getMethods(): array
    {
        $reflection = $this->getReflection();

        $methods = [];
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $method) {
            $method = new MethodParser($method);

            // ignore constructor
            if ('__construct' === $method->getName()) {
                continue;
            }

            // ignore from external class (e.g.: parent class)
            if ($reflection->getName() !== $method->getDeclaringClass()->getName()) {
                continue;
            }

            // ignore accessors
            foreach ($method->getDeclaringClass()->getProperties() as $property) {
                if (\in_array($method->getName(), (new PropertyParser($property))->getAccessors(), true)) {
                    continue 2;
                }
            }

            $methods[] = $method;
        }

        return $methods;
    }

    public function getMethod(string $name): MethodParser
    {
        return new MethodParser($this->getReflection()->getMethod($name));
    }

    public function getReflection(): \ReflectionClass
    {
        return $this->reflection;
    }
}
