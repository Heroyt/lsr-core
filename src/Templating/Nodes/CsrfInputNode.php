<?php

namespace Lsr\Core\Templating\Nodes;

use Generator;
use Latte\CompileException;
use Latte\Compiler\Node;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

class CsrfInputNode extends StatementNode
{

    private ExpressionNode $name;

    /**
     * @param  Tag  $tag
     *
     * @return Node
     * @throws CompileException
     */
    public static function create(Tag $tag) : Node {
        $tag->expectArguments();
        $node = new self();
        $node->name = $tag->parser->parseExpression();
        return $node;
    }

    public function print(PrintContext $context) : string {
        return $context->format(
          <<<'XX'
			echo '<input type="hidden" name="_csrf_token" value="'.hash_hmac('sha256', %node, formToken(%node)).'" />' %line;
			XX,
          $this->name,
          $this->name,
          $this->position,
        );
    }

    public function &getIterator() : Generator {
        yield $this->name;
    }
}