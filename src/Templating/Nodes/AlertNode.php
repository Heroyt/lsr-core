<?php

namespace Lsr\Core\Templating\Nodes;

use Generator;
use Latte\CompileException;
use Latte\Compiler\Node;
use Latte\Compiler\Nodes\Php\ArrayItemNode;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\ModifierNode;
use Latte\Compiler\Nodes\Php\Scalar\StringNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

class AlertNode extends StatementNode
{

    private ArrayNode $args;
    public ModifierNode $modifier;

    /**
     * @param  Tag  $tag
     *
     * @return Node
     * @throws CompileException
     */
    public static function create(Tag $tag) : Node {
        $tag->expectArguments();
        $node = new self();
        $node->args = $tag->parser->parseArguments();
        $node->modifier = $tag->parser->parseModifier();
        $node->modifier->escape = !$node->modifier->removeFilter('noescape');
        return $node;
    }

    /**
     * @param  Tag  $tag
     *
     * @return Node
     * @throws CompileException
     */
    public static function createDanger(Tag $tag) : Node {
        $tag->expectArguments();
        $node = new self();
        $node->args = $tag->parser->parseArguments();
        $node->args->items[] = new ArrayItemNode(
          new StringNode("danger")
        );
        return $node;
    }

    /**
     * @param  Tag  $tag
     *
     * @return Node
     * @throws CompileException
     */
    public static function createSuccess(Tag $tag) : Node {
        $tag->expectArguments();
        $node = new self();
        $node->args = $tag->parser->parseArguments();
        $node->args->items[] = new ArrayItemNode(
          new StringNode("success")
        );
        return $node;
    }

    /**
     * @param  Tag  $tag
     *
     * @return Node
     * @throws CompileException
     */
    public static function createInfo(Tag $tag) : Node {
        $tag->expectArguments();
        $node = new self();
        $node->args = $tag->parser->parseArguments();
        $node->args->items[] = new ArrayItemNode(
          new StringNode("info")
        );
        return $node;
    }

    /**
     * @param  Tag  $tag
     *
     * @return Node
     * @throws CompileException
     */
    public static function createWarning(Tag $tag) : Node {
        $tag->expectArguments();
        $node = new self();
        $node->args = $tag->parser->parseArguments();
        $node->args->items[] = new ArrayItemNode(
          new StringNode("warning")
        );
        return $node;
    }

    public function print(PrintContext $context) : string {
        return $context->format(
          <<<'XX'
			echo alert(%args) %line;
			XX,
          $this->args,
          $this->position,
        );
    }

    public function &getIterator() : Generator {
        foreach ($this->args as $arg) {
            yield $arg;
        }
    }
}