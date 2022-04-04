<?php
declare(strict_types = 1);

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Translation;

use Latte\Runtime\Template;
use Nette\SmartObject;
use Nette\Utils\Strings;

class PrefixedTranslator implements ITranslator
{

    use SmartObject;

    /** @var ITranslator|Translator|PrefixedTranslator */
    private $translator;

    /** @var string */
    private $prefix;

    /**
     * @param string $prefix
     * @param ITranslator $translator
     * @throws InvalidArgumentException
     */
    final public function __construct($prefix, ITranslator $translator)
    {
        if (!$translator instanceof Translator && !$translator instanceof PrefixedTranslator) {
            throw new InvalidArgumentException(sprintf('The given translator must be instance of %s or %s, bug %s was given', Translator::class, self::class, get_class($translator)));
        }

        if ($translator instanceof PrefixedTranslator) {
            $translator = $translator->unwrap();
        }

        $this->translator = $translator;
        $this->prefix = rtrim($prefix, '.');
    }

    /**
     * @param string|Phrase $message
     * @param array<string|int,string>|string|NULL ...$arg
     * @return string
     */
    public function translate($message, ...$arg): string
    {
        $translationString = ($message instanceof Phrase ? $message->message : $message);
        $prefix = $this->prefix . '.';

        $count = $arg[0] ?? null;
        $parameters = $arg[1] ?? [];
        $domain = $arg[2] ?? null;
        $locale = $arg[3] ?? null;

        if (Strings::startsWith((string) $message, '//')) {
            $prefix = null;
            $translationString = Strings::substring($translationString, 2);
        }

        if ($message instanceof Phrase) {
            return $this->translator->translate(new Phrase($prefix . $translationString, $message->count, $message->parameters, $message->domain, $message->locale));
        }

        return $this->translator->translate($prefix . $translationString, $count, (array) $parameters, $domain, $locale);
    }

    /**
     * @return ITranslator
     */
    public function unwrap()
    {
        return $this->translator;
    }

    /**
     * @param Template $template
     * @return ITranslator
     */
    public function unregister(Template $template)
    {
        $translator = $this->unwrap();
        self::overrideTemplateTranslator($template, $translator);
        return $translator;
    }

    /**
     * @param Template $template
     * @param string $prefix
     * @throws InvalidArgumentException
     * @return ITranslator
     */
    public static function register(Template $template, $prefix)
    {
        $translator = new static($prefix, $template->global->translator);
        self::overrideTemplateTranslator($template, $translator);
        return $translator;
    }

    private static function overrideTemplateTranslator(Template $template, ITranslator $translator): void
    {
        $template->getEngine()->addFilter('translate', [new TemplateHelpers($translator), 'translate']);
    }

}
