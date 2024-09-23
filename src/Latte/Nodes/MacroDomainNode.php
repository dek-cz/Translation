<?php

namespace Kdyby\Translation\Latte\Nodes;

use Kdyby\Translation\PrefixedTranslator;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\Php\Scalar\StringNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Compiler\TemplateParser;

class MacroDomainNode extends StatementNode
{

    public ExpressionNode $prefix;

    public AreaNode $content;

//    public function __construct(Tag $tag, ExpressionNode $prefix)
//    {
//        $this->tag = $tag;
//        $this->prefix = $prefix;
//    }


    /** @return \Generator<int, ?array<mixed>, array{AreaNode, ?Tag}, TranslatorNode> */
    public static function create(
        Tag $tag
    ): \Generator
    {
        $tag->expectArguments();
        $variable = $tag->parser->parseUnquotedStringOrExpression();

        $node = new static;
        $node->prefix = $variable;
        [$node->content] = yield;
        return $node;
    }

    public function print(PrintContext $context): string
    {

//        if ($this->content->closing) {
//            if (isset($this->content->arguments) && $this->content->arguments !== '') {
//                return $context->write('$_translator->unregister($this);');
//            }
//            return $context->write('');
//        }

        return $context->format(
                '$_translator = %raw::register($this, %node);',
                PrefixedTranslator::class,
                $this->prefix
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->prefix;
    }

}
