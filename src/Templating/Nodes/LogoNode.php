<?php

namespace Lsr\Core\Templating\Nodes;

use Latte\Compiler\Node;
use Latte\Compiler\Nodes\Php\ModifierNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

class LogoNode extends StatementNode
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
        $node->modifier->escape = false;
        return $node;
    }

    public function print(PrintContext $context) : string {
        return $context->format(
          <<<'XX'
			echo \Lsr\Core\Tools\LogoHelper::getLogoHtml() %line;
			XX,
          $this->position,
        );
    }
}