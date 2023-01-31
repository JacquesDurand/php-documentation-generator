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

use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;

final class ParameterParser extends AbstractParser
{
    public function __construct(private readonly \ReflectionParameter $reflection)
    {
    }

    public function getType(): ?TypeParser
    {
        if ($this->getReflection()->hasType()) {
            return new TypeParser($this->getReflection()->getType());
        }

        return null;
    }

    public function getAdditionalTypes(): ?ParamTagValueNode
    {
        // retrieve additional types from method doc
        $phpDoc = (new MethodParser($this->getReflection()->getDeclaringFunction()))->getPhpDoc();
        foreach ($phpDoc->getParamTagValues() as $param) {
            if ($this->getReflection()->getName() === substr($param->parameterName, 1)) {
                return $param;
            }
        }

        return null;
    }

    public function getReflection(): \ReflectionParameter
    {
        return $this->reflection;
    }
}
