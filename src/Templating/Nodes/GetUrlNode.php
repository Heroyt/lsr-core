<?php

namespace Lsr\Core\Templating\Nodes;

use Latte\Compiler\Node;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

class GetUrlNode extends StatementNode
{

	/**
	 * @param Tag $tag
	 *
	 * @return Node
	 */
	public static function create(Tag $tag) : Node {
		return new self();
	}

	public function print(PrintContext $context) : string {
		return $context->format(
			<<<'XX'
			echo \Lsr\Core\App::getUrl() %line;
			XX,
			$this->position,
		);
	}

	public function &getIterator() : \Generator {
		if (false) {
			yield;
		}
	}
}