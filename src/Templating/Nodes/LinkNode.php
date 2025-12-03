<?php

namespace Lsr\Core\Templating\Nodes;

use InvalidArgumentException;
use Latte\CompileException;
use Latte\Compiler\Node;
use Latte\Compiler\NodeHelpers;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\ModifierNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\Nodes\TextNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Lsr\Core\App;
use Lsr\Core\Links\Generator;

class LinkNode extends StatementNode
{
    public ModifierNode $modifier;
    public ArrayNode $args;

    public ?TextNode $static = null;

    /**
     * @param  Tag  $tag
     *
     * @return Node
     * @throws CompileException
     */
    public static function create(Tag $tag) : Node {
        $tag->expectArguments();

        $node = $tag->node = new self;
        $node->args = $args = $tag->parser->parseArguments();
        $node->modifier = $tag->parser->parseModifier();
        $node->modifier->escape = !$node->modifier->removeFilter('noescape');

        try {
            /** @var array<array<string|int,string>|string> $constArgs */
            $constArgs = NodeHelpers::toValue($args, constants: true);
            /** @var Generator $generator */
            $generator = App::getService('links.generator');
            $node->static = new TextNode($generator->getLink(...$constArgs));
        } catch (InvalidArgumentException) {
        }
        return $node;
    }

    public function print(PrintContext $context) : string {
        if (isset($this->static)) {
            return $context->format(
              <<<'XX'
					$ʟ_fi = new LR\FilterInfo(%dump);
					echo %modifyContent(%dump) %line;
					XX,
              $context->getEscaper()->export(),
              $this->modifier,
              $this->static->content,
              $this->position,
            );
        }

        return $context->format(
          <<<'XX'
			$ʟ_fi = new LR\FilterInfo(%dump);
			echo %modifyContent(\Lsr\Core\App::getService('links.generator')->getLink(%args)) %line;
			XX,
          $context->getEscaper()->export(),
          $this->modifier,
          $this->args,
          $this->position,
        );
    }

    public function &getIterator() : \Generator {
        if (isset($this->static)) {
            yield $this->static;
        }
        else {
            foreach ($this->args as $arg) {
                yield $arg;
            }
        }
        yield $this->modifier;
    }
}