<?php

namespace Lsr\Core\Templating\Nodes;

use Generator;
use Latte\Compiler\Node;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\Php\ModifierNode;
use Latte\Compiler\Nodes\Php\Scalar\StringNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Lsr\Core\App;
use Lsr\Helpers\Csrf\TokenHelper;

class CsrfNode extends StatementNode
{
    private ArrayNode $args;
    private ModifierNode $modifier;

    private ExpressionNode $prefix;

    public static function create(Tag $tag) : Node {
        $node = new self;
        $node->args = $tag->parser->parseArguments();
        $node->modifier = $tag->parser->parseModifier();
        $node->modifier->escape = false;

        $args = $node->args->toArguments();
        $node->prefix = isset($args[0]) ? $args[0]->value : new StringNode('');
        return $node;
    }

    public function print(PrintContext $context) : string {
        return $context->format(
          <<<'XX'
          echo %raw::getServiceByType(%dump)->formToken(%node) %line;
          XX,
          App::class,
          TokenHelper::class,
          $this->prefix,
          $this->position,
        );
    }

    public function &getIterator() : Generator {
        yield $this->prefix;
    }
}