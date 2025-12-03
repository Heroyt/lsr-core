<?php

namespace Lsr\Core\Templating\Nodes;

use Generator;
use Latte\CompileException;
use Latte\Compiler\Node;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\ModifierNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

class LangNode extends StatementNode
{
    /**
     * @var ArrayNode
     */
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

    public function print(PrintContext $context) : string {
        return $context->format(
          <<<'XX'
			echo lang(%args) %line;
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