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

final class TypeParser extends AbstractParser
{
    public function __construct(private readonly \ReflectionType $reflection)
    {
    }

    public function getFileName(): string
    {
        return (new ClassParser(new \ReflectionClass($this->getReflection()->getName())))->getFileName();
    }

    public function isUnion(): bool
    {
        return $this->reflection instanceof \ReflectionUnionType;
    }

    public function isIntersection(): bool
    {
        return $this->reflection instanceof \ReflectionIntersectionType;
    }

    public function isNamed(): bool
    {
        return $this->reflection instanceof \ReflectionNamedType;
    }

    public function isClass(): bool
    {
        return $this->isNamed() && !$this->getReflection()->isBuiltin() && class_exists($this->getReflection()->getName());
    }

    public function getClass(): ?ClassParser
    {
        if ($this->isClass()) {
            return new ClassParser(new \ReflectionClass($this->getReflection()->getName()));
        }

        return null;
    }

    /**
     * @return TypeParser[]
     */
    public function getTypes(): array
    {
        if (!$this->isUnion() && !$this->isIntersection()) {
            return [$this];
        }

        return array_map(fn (\ReflectionType $type) => new self($type), $this->getReflection()->getTypes());
    }

    public function getReflection(): \ReflectionType
    {
        return $this->reflection;
    }
}
