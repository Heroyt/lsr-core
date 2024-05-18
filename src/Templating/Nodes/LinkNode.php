<?php

namespace Lsr\Core\Templating\Nodes;

use InvalidArgumentException;
use Latte\CompileException;
use Latte\Compiler\Node;
use Latte\Compiler\NodeHelpers;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\ModifierNode;
use Latte\Compiler\Nodes\Php\Scalar\StringNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Lsr\Core\App;
use Lsr\Core\Links\Generator;

class LinkNode extends StatementNode
{
    public ModifierNode $modifier;
    public ArrayNode $args;

    /**
     * @param  Tag  $tag
     *
     * @return Node
     * @throws CompileException
     */
    public static function create(Tag $tag) : Node {
        $tag->expectArguments();
        $args = $tag->parser->parseArguments();

        try {
            /** @var array<array<string|int,string>|string> $constArgs */
            $constArgs = NodeHelpers::toValue($args, constants: true);
            /** @var Generator $generator */
            $generator = App::getService('links.generator');
            return new StringNode($generator->getLink(...$constArgs));
        } catch (InvalidArgumentException) {
        }

        $node = new self();
        $node->args = $args;
        return $node;
    }

    public function print(PrintContext $context) : string {
        return $context->format(
          <<<'XX'
			echo \Lsr\Core\App::getService('links.generator')->getLink(%args) %line;
			XX,
          $this->args,
          $this->position,
        );
    }

    public function &getIterator() : \Generator {
        yield $this;
    }
}