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

namespace ApiPlatform\PDGBundle\Twig;

use ApiPlatform\PDGBundle\Services\ConfigurationHandler;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocChildNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use Twig\TwigFilter;

class MarkdownExtendedExtension extends MarkdownExtension
{
    private readonly Lexer $lexer;
    private readonly PhpDocParser $parser;

    public function __construct(ConfigurationHandler $configuration)
    {
        parent::__construct($configuration);

        $this->lexer = new Lexer();
        $this->parser = new PhpDocParser(new TypeParser(new ConstExprParser()), new ConstExprParser());
    }

    public function getFilters(): array
    {
        return parent::getFilters() + [
            new TwigFilter('mdx_sanitize', [$this, 'sanitize']),
        ];
    }

    public function sanitize(string $string): string
    {
        $tokens = new TokenIterator($this->lexer->tokenize($string));
        $tokens->consumeTokenType(Lexer::TOKEN_END);
        /** @var PhpDocTextNode[] $nodes */
        $nodes = array_filter($this->parser->parse($tokens)->children, static function (PhpDocChildNode $child): bool {
            return $child instanceof PhpDocTextNode;
        });

        foreach ($nodes as $node) {
            $text = $node->text;
            // @TODO: this should be handled by the Javascript using `md` files as `mdx` we should not need this here
            // indeed {@see} breaks mdx as it thinks that its a React component
            if (str_contains($text, '@see')) {
                $text = str_replace(['{@see', '}'], ['see', ''], $text);
            }
            $string.= $text.\PHP_EOL;
        }

        return trim($string);
    }
}