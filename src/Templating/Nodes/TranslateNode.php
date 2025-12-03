<?php

/**
 * This file is part of the Latte (https://latte.nette.org)
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Lsr\Core\Templating\Nodes;

use Generator;
use Latte\Compiler\NodeHelpers;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\NopNode;
use Latte\Compiler\Nodes\Php;
use Latte\Compiler\Nodes\Php\ModifierNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\Nodes\TextNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Essential\TranslatorExtension;
use Lsr\Core\Translations;


/**
 * {translate} ... {/translate}
 */
final class TranslateNode extends StatementNode
{
    public AreaNode $content;
    public ModifierNode $modifier;


    /**
     * @return Generator<int, ?string[], array{AreaNode, ?Tag}, static|NopNode>
     */
    public static function create(Tag $tag, Translations $translator) : Generator {
        $tag->outputMode = $tag::OutputKeepIndentation;

        $node = $tag->node = new self;
        $args = $tag->parser->parseArguments();
        $node->modifier = $tag->parser->parseModifier();
        $node->modifier->escape = !$node->modifier->removeFilter('noescape');
        if ($tag->void) {
            return new NopNode;
        }

        [$node->content] = yield;

        if ($text = NodeHelpers::toText($node->content)) {
            if (is_array($values = TranslatorExtension::toValue($args))) {
                $translation = $translator->translate($text, ...$values);
                $node->content = new TextNode($translation);
                return $node;
            }
            $node->content = new TextNode($text);
        }

        array_unshift(
          $node->modifier->filters,
          new Php\FilterNode(new Php\IdentifierNode('translate'), $args->toArguments())
        );

        return $node;
    }


    public function print(PrintContext $context) : string {
        if ($this->content instanceof TextNode) {
            return $context->format(
              <<<'XX'
					$ʟ_fi = new LR\FilterInfo(%dump);
					echo %modifyContent(%dump) %line;
					XX,
              $context->getEscaper()->export(),
              $this->modifier,
              $this->content->content,
              $this->position,
            );

        }

        return $context->format(
          <<<'XX'
					ob_start(fn() => ''); try {
						%node
					} finally {
						$ʟ_tmp = ob_get_clean();
					}
					$ʟ_fi = new LR\FilterInfo(%dump);
					echo %modifyContent($ʟ_tmp) %line;
					XX,
          $this->content,
          $context->getEscaper()->export(),
          $this->modifier,
          $this->position,
        );
    }


    public function &getIterator() : Generator {
        yield $this->content;
        yield $this->modifier;
    }
}
