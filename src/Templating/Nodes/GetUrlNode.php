<?php

namespace Lsr\Core\Templating\Nodes;

use Generator;
use Latte\Compiler\Node;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;

class GetUrlNode extends StatementNode
{

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
			echo \Lsr\Core\App::getInstance()->getUrl() %line;
			XX,
          $this->position,
        );
    }

    public function &getIterator() : Generator {
        yield $this;
    }
}