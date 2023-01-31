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

use ApiPlatform\PDGBundle\Services\Reference\Parser\PromotedPropertyDefaultValueNodeVisitor;
use ApiPlatform\PDGBundle\Services\Reference\Parser\PropertyDefaultValueNodeVisitor;
use PhpParser\Node;
use PhpParser\NodeTraverser;

final class PropertyHelper extends AbstractHelper
{
    public function shouldBeSkipped(\ReflectionProperty $property): bool
    {
        return str_contains($this->getModifier($property), 'private') && !$this->getAccessors($property);
    }

    public function getType(\ReflectionProperty $property): string
    {
        $type = $property->getType();

        if (!$type) {
            return '`mixed`';
        }

        if ($type instanceof \ReflectionUnionType) {
            return implode('|', array_map(function (\ReflectionNamedType $namedType) {
                return $this->outputFormatter->linkClasses($namedType);
            }, $type->getTypes()));
        }

        if ($type instanceof \ReflectionIntersectionType) {
            return implode('&', array_map(function (\ReflectionNamedType $namedType) {
                return $this->outputFormatter->linkClasses($namedType);
            }, $type->getTypes()));
        }

        if ($type instanceof \ReflectionNamedType) {
            return $this->outputFormatter->linkClasses($type);
        }

        return sprintf('`%s`', $type);
    }

    public function hasDefaultValue(\ReflectionProperty $property): bool
    {
        return '' !== $this->getDefaultValue($property);
    }

    public function getDefaultValue(\ReflectionProperty $property): string
    {
        if ($property->getDeclaringClass()->isInternal()) {
            return '';
        }

        $traverser = new NodeTraverser();
        $stmts = $this->parser->parse(file_get_contents($property->getDeclaringClass()->getFileName()));

        $visitor = $property->isPromoted() ? new PromotedPropertyDefaultValueNodeVisitor($property) : new PropertyDefaultValueNodeVisitor($property);
        $traverser->addVisitor($visitor);
        $traverser->traverse($stmts);

        $defaultValue = $visitor->defaultValue;

        return match (true) {
            null === $defaultValue => '',
            $defaultValue instanceof Node\Scalar => $defaultValue->getAttribute('rawValue'),
            $defaultValue instanceof Node\Expr\ConstFetch => $defaultValue->name->parts[0],
            $defaultValue instanceof Node\Expr\New_ => sprintf('new %s()', $defaultValue->class->parts[0]),
            $defaultValue instanceof Node\Expr\Array_ => $this->outputFormatter->arrayNodeToString($defaultValue),
            $defaultValue instanceof Node\Expr\ClassConstFetch => $defaultValue->class->parts[0].'::'.$defaultValue->name->name
        };
    }

    public function getAccessors(\ReflectionProperty $property)
    {
        $propertyName = $property->getName();
        $accessors = [];

        foreach ($property->getDeclaringClass()->getMethods() as $method) {
            switch ($method->getName()) {
                case 'get'.ucfirst($propertyName):
                case 'set'.ucfirst($propertyName):
                case 'is'.ucfirst($propertyName):
                    $accessors[] = $method->getName();
                    break;
                default:
                    continue 2;
            }
        }

        return $accessors;
    }
}
