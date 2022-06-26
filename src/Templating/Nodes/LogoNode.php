<?php

namespace Lsr\Core\Templating\Nodes;

use Latte\Compiler\Node;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;

class LogoNode extends StatementNode
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
			echo \Lsr\Helpers\Tools\LogoHelper::getLogoHtml() %line;
			XX,
			$this->position,
		);
	}
}