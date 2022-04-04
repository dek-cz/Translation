<?php
declare(strict_types = 1);

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Translation\Latte;

use Kdyby\Translation\PrefixedTranslator;
use Latte\CompileException;
use Latte\Compiler;
use Latte\MacroNode;
use Latte\Macros\MacroSet;
use Latte\PhpWriter;
use Latte\Strict;

class TranslateMacros extends MacroSet
{

    use Strict;

    final public function __construct(Compiler $compiler)
    {
        parent::__construct($compiler);
    }

    public static function install(Compiler $compiler): self
    {
        $me = new static($compiler);
        /** @var TranslateMacros $me */
        $me->addMacro('_', [$me, 'macroTranslate'], [$me, 'macroTranslate']);
        $me->addMacro('translator', [$me, 'macroDomain'], [$me, 'macroDomain']);

        return $me;
    }

    /**
     * {_$var |modifiers}
     * {_$var, $count |modifiers}
     * {_"Sample message", $count |modifiers}
     * {_some.string.id, $count |modifiers}
     */
    public function macroTranslate(MacroNode $node, PhpWriter $writer): string
    {
        if ($node->closing) {
            if (strpos($node->content, '<?php') === false) {
                $value = var_export($node->content, true);
                $node->content = '';
            } else {
                $node->openingCode = '<?php ob_start(function () {}) ?>' . $node->openingCode;
                $value = 'ob_get_clean()';
            }

            return $writer->write('$ʟ_fi = new LR\FilterInfo(%var); echo %modifyContent($this->filters->filterContent("translate", $ʟ_fi, %raw))', $node->context[0], $value);
        } elseif ($node->args !== '') {
            $node->empty = true;
            if ($this->containsOnlyOneWord($node)) {
                return $writer->write('echo %modify(call_user_func($this->filters->translate, %node.word))');
            } else {
                return $writer->write('echo %modify(call_user_func($this->filters->translate, %node.word, %node.args))');
            }
        }

        return '';
    }

    /**
     * @param MacroNode $node
     * @param PhpWriter $writer
     * @throws CompileException for invalid domain
     * @return string|NULL
     */
    public function macroDomain(MacroNode $node, PhpWriter $writer): ?string
    {
        if ($node->closing) {
            if ($node->content !== null && $node->content !== '') {
                return $writer->write('$_translator->unregister($this);');
            }
        } else {
            if ($node->empty) {
                throw new CompileException('Expected message prefix, none given');
            }

            return $writer->write('$_translator = ' . PrefixedTranslator::class . '::register($this, %node.word);');
        }

        return null;
    }

    /**
     * @param MacroNode $node
     * @return bool
     */
    private function containsOnlyOneWord(MacroNode $node): bool
    {
        $result = trim($node->tokenizer->joinUntil(',')) === trim($node->args);
        $node->tokenizer->reset();
        return $result;
    }

}
