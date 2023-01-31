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
        return $this->getReflection()->hasType() ? new TypeParser($this->getReflection()->getType()) : null;
    }

    public function getDocComment(): string|false
    {
        // property has a docComment
        if ($docComment = parent::getDocComment()) {
            return $docComment;
        }

        // property does not have any docComment: try to retrieve it from constructor
        $class = $this->getReflection()->getDeclaringClass();
        if ($class->hasMethod('__construct')) {
            foreach ((new MethodParser($class->getMethod('__construct')))->getPhpDoc()->getParamTagValues() as $param) {
                if ($this->getReflection()->getName() === substr($param->parameterName, 1)) {
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

        // retrieve types from constructor doc
        $class = $this->getReflection()->getDeclaringClass();
        if ($class->hasMethod('__construct')) {
            foreach ((new MethodParser($class->getMethod('__construct')))->getPhpDoc()->getParamTagValues() as $paramTagValue) {
                if ($this->getReflection()->getName() === substr($paramTagValue->parameterName, 1)) {
                    return $paramTagValue;
                }
            }
        }

        return null;
    }

    public function getDefaultValue()
    {
        // ignore property without default value or related to internal classes
        if (!$this->getReflection()->hasDefaultValue() || $this->getReflection()->getDeclaringClass()->isInternal()) {
            return null;
        }

        return $this->getReflection()->getDefaultValue();
    }

    /**
     * @return MethodParser[]
     */
    public function getAccessors()
    {
        $propertyName = $this->getReflection()->getName();
        $accessors = [];

        foreach ($this->getReflection()->getDeclaringClass()->getMethods() as $method) {
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
