<?php

namespace Lsr\Core\Templating\Nodes;

use Latte\CompileException;
use Latte\Compiler\Node;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

class LinkNode extends StatementNode
{
	/**
	 * @var ArrayNode
	 */
	private ArrayNode $args;

	/**
	 * @param Tag $tag
	 *
	 * @return Node
	 * @throws CompileException
	 */
	public static function create(Tag $tag) : Node {
		$tag->expectArguments();
		$node = new self();
		$node->args = $tag->parser->parseArguments();
		return $node;
	}

	public function print(PrintContext $context) : string {
		return $context->format(
			<<<'XX'
			echo \Lsr\Core\App::getLink(%args) %line;
			XX,
			$this->args,
			$this->position,
		);
	}
}