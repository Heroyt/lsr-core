<?php

namespace Lsr\Core\Templating\Nodes;

use Generator;
use Latte\Compiler\Node;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;

class CsrfNode extends StatementNode
{
    use NoIteratorNode;

    /**
     *
     * @return Node
     */
    public static function create() : Node {
        return new self();
    }

    public function print(PrintContext $context) : string {
        return $context->format(
          <<<'XX'
			echo formToken() %line;
			XX,
          $this->position,
        );
    }
}