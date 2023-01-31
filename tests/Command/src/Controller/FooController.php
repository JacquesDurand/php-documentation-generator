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

namespace ApiPlatform\PDGBundle\Tests\Command\App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class FooController
{
    /**
     * @param Request $request a request
     *
     * @return JsonResponse a JSON response
     */
    public function __invoke(Request $request): Response
    {
        return new JsonResponse(['foo' => 'bar']);
    }
}
