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

use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagValueNode;

final class PropertyParser extends AbstractParser
{
    public function __construct(private readonly \ReflectionProperty $reflection)
    {
    }

    public function getModifier(): string
    {
        return implode(' ', \Reflection::getModifierNames($this->getReflection()->getModifiers()));
    }

    public function getType(): ?TypeParser
    {
        $reflection = $this->getReflection();

        return $reflection->hasType() ? new TypeParser($reflection->getType()) : null;
    }

    public function getDocComment(): string|false
    {
        // property has a docComment
        if ($docComment = parent::getDocComment()) {
            return $docComment;
        }

        $reflection = $this->getReflection();

        // property does not have any docComment: try to retrieve it from constructor
        $class = $reflection->getDeclaringClass();
        if ($class->hasMethod('__construct')) {
            foreach ((new MethodParser($class->getMethod('__construct')))->getPhpDoc()->getParamTagValues() as $param) {
                if ($reflection->getName() === substr($param->parameterName, 1)) {
                    // docComment MUST be a comment to be parsed by "getPhpDoc"
                    // comment is removed in "getSummary" method
                    return sprintf(<<<EOT
/**
 * %s
 */
EOT
                        , $param->description);
                }
            }
        }

        return false;
    }

    public function getAdditionalTypes(): ?PhpDocTagValueNode
    {
        // retrieve "@var" tags from property doc
        if ($varTagValues = $this->getPhpDoc()->getVarTagValues()) {
            foreach ($varTagValues as $varTagValue) {
                return $varTagValue;
            }
        }

        $reflection = $this->getReflection();

        // retrieve types from constructor doc
        $class = $reflection->getDeclaringClass();
        if ($class->hasMethod('__construct')) {
            foreach ((new MethodParser($class->getMethod('__construct')))->getPhpDoc()->getParamTagValues() as $paramTagValue) {
                if ($reflection->getName() === substr($paramTagValue->parameterName, 1)) {
                    return $paramTagValue;
                }
            }
        }

        return null;
    }

    public function getDefaultValue()
    {
        $reflection = $this->getReflection();

        // ignore property without default value or related to internal classes
        if (!$reflection->hasDefaultValue() || $reflection->getDeclaringClass()->isInternal()) {
            return null;
        }

        return $reflection->getDefaultValue();
    }

    /**
     * @return MethodParser[]
     */
    public function getAccessors()
    {
        $reflection = $this->getReflection();
        $propertyName = $reflection->getName();
        $accessors = [];

        foreach ($reflection->getDeclaringClass()->getMethods() as $method) {
            switch ($method->getName()) {
                case 'get'.ucfirst($propertyName):
                case 'is'.ucfirst($propertyName):
                case 'has'.ucfirst($propertyName):
                    $accessors[] = new MethodParser($method);
                    break;
                default:
                    continue 2;
            }
        }

        return $accessors;
    }

    public function getReflection(): \ReflectionProperty
    {
        return $this->reflection;
    }
}
