<?php

namespace Lsr\Core\Templating;

use Closure;
use InvalidArgumentException;
use Latte\CompileException;
use Latte\Compiler\NodeHelpers;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\Php\FilterNode;
use Latte\Compiler\Nodes\Php\IdentifierNode;
use Latte\Compiler\Nodes\Php\Scalar\StringNode;
use Latte\Compiler\Tag;
use Latte\Engine;
use Latte\Essential\Nodes\PrintNode;
use Latte\Extension;
use Latte\Runtime\FilterInfo;
use Lsr\Core\Translations;

final class TranslatorExtension extends Extension
{


    public function __construct(private readonly Translations $translator) {}


    /**
     * @return array{_: array{0: TranslatorExtension, 1: string}, translate: Closure}
     */
    public function getTags() : array {
        return [
          '_'         => [$this, 'parseTranslate'],
          'translate' => fn(Tag $tag) => yield from Nodes\TranslateNode::create($tag, $this->translator),
        ];
    }


    /**
     * @return array{translate: Closure}
     */
    public function getFilters() : array {
        return [
          'translate' => fn(FilterInfo $fi, ...$args) : string => $this->translator->translate(...$args),
        ];
    }


    public function getCacheKey(Engine $engine) : string {
        return $this->translator->getLang();
    }


    /**
     * {_ ...}
     *
     * @throws CompileException
     */
    public function parseTranslate(Tag $tag) : PrintNode {
        $tag->outputMode = $tag::OutputKeepIndentation;
        $tag->expectArguments();
        $node = new PrintNode;
        $node->expression = $tag->parser->parseUnquotedStringOrExpression();
        $args = new ArrayNode;
        if ($tag->parser->stream->tryConsume(',')) {
            $args = $tag->parser->parseArguments();
        }

        $node->modifier = $tag->parser->parseModifier();
        $node->modifier->escape = true;

        if (($expr = self::toValue($node->expression)) && is_array($values = self::toValue($args))) {
            /** @var string $expr */
            $translation = $this->translator->translate($expr, ...$values);
            $node->expression = new StringNode($translation);
            return $node;
        }

        array_unshift(
          $node->modifier->filters,
          new FilterNode(new IdentifierNode('translate'), $args->toArguments())
        );
        return $node;
    }


    public static function toValue(ExpressionNode $args) : mixed {
        try {
            return NodeHelpers::toValue($args, constants: true);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}