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

final class ChainHelper
{
    /**
     * @param HelperInterface[] $helpers
     */
    public function __construct(private readonly array $helpers)
    {
    }

    public function getProperty(): PropertyHelper
    {
        return $this->helpers['property'];
    }

    public function getMethod(): MethodHelper
    {
        return $this->helpers['method'];
    }
}
