<?php

namespace Lsr\Core\Templating\Nodes;

use Generator;
use Latte\CompileException;
use Latte\Compiler\Node;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\Php\ModifierNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Lsr\Core\App;
use Lsr\Helpers\Csrf\TokenHelper;

class CsrfInputNode extends StatementNode
{

    private ExpressionNode $name;
    private ModifierNode $modifier;

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
        $node->modifier = $tag->parser->parseModifier();
        $node->modifier->escape = false;
        return $node;
    }

    public function print(PrintContext $context) : string {
        return $context->format(
          <<<'XX'
          echo '<input type="hidden" name="_csrf_token" value="'.hash_hmac('sha256', %node, %raw::getServiceByType(%raw)->(%node)).'" />' %line;
          XX,
          $this->name,
          App::class,
          TokenHelper::class,
          $this->name,
          $this->position,
        );
    }

    public function &getIterator() : Generator {
        yield $this->name;
    }
}