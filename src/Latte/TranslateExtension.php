<?php

namespace Kdyby\Translation\Latte;

use Kdyby\Translation\Latte\Nodes\MacroDomainNode;
use Kdyby\Translation\PrefixedTranslator;
use Latte\CompileException;
use Latte\Extension;
use Latte\Compiler\Tag;
use Latte\Compiler\PrintContext;
use Latte\Compiler\TemplateParser;

class TranslateExtension extends Extension
{
    public function getTags(): array
    {
        return [
            '_' => [$this, 'macroTranslate'], // Přiřazení makra pro překlad
            'translator' => [MacroDomainNode::class, 'create'], // Přiřazení makra pro doménu překladů
        ];
    }

    /**
     * Zpracování makra {_$var |modifiers} nebo {_"Message", $count |modifiers}
     */
    public function macroTranslate(Tag $tag, PrintContext $context): string
    {
        // Pokud jde o uzavírací tag
        if ($tag->isNAttribute()) {
            throw new CompileException('Attribute macros are not supported.');
        }

        if ($tag->closing) {
            // Získání textu mezi otvíracím a uzavíracím tagem
            $value = $tag->parser->stream->getText();
            return $context->format(
                'echo %modify($this->filters->translate(%raw));',
                var_export($value, true)
            );
        }

        // Parsování argumentů
        $args = $tag->parser->parseUnquotedStringOrExpression();

        // Zpracování pro jedno slovo
        if ($args) {
            return $context->format(
                'echo %modify($this->filters->translate(%node));',
                $args
            );
        }

        return '';
    }

//    /**
//     * Zpracování makra {translator} pro překladatelské domény
//     */
//    public function macroDomain(Tag $tag): MacroDomainNode
//    {
//        
//        // Zpracování prefixu překladové domény
//        $prefix = $tag->parser->parseUnquotedStringOrExpression();
//        if (!$prefix) {
//            throw new CompileException('Očekává se prefix zprávy, žádný nebyl uveden.');
//        }
//
//        return new MacroDomainNode($tag, $prefix);
//    }
}
