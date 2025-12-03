<?php

namespace Lsr\Core\Templating\Nodes;

use Latte\Compiler\Node;
use Latte\Compiler\Nodes\Php\ModifierNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

class GetUrlNode extends StatementNode
{
    use NoIteratorNode;

    public ModifierNode $modifier;

    /**
     *
     * @return Node
     */
    public static function create(Tag $tag): Node
    {
        $node = new self();
        $node->modifier = $tag->parser->parseModifier();
        $node->modifier->escape = !$node->modifier->removeFilter('noescape');
        return $node;
    }

    public function print(PrintContext $context) : string {
        return $context->format(
          <<<'XX'
			echo \Lsr\Core\App::getInstance()->getBaseUrl() %line;
			XX,
          $this->position,
        );
    }
}