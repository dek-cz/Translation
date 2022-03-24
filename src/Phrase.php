<?php declare(strict_types = 1);

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Translation;

use Nette\SmartObject;
use Throwable;

/**
 * Object wrapper for message that can store default parameters and related information for translation.
 */
class Phrase
{

    use SmartObject;

    /** @var string */
    public $message;

    /** @var int|NULL */
    public $count;

    /** @var array<string|int,string> */
    public $parameters;

    /** @var string|NULL */
    public $domain;

    /** @var string|NULL */
    public $locale;

    /** @var Translator|NULL */
    private $translator;

    /**
     * @param string $message
     * @param int|array<string|int,string>|NULL $count
     * @param string|array<string|int,string>|NULL $parameters
     * @param string|NULL $domain
     * @param string|NULL $locale
     */
    public function __construct($message, $count = null, $parameters = null, $domain = null, $locale = null)
    {
        $this->message = $message;

        if (is_array($count)) {
            $locale = ($domain !== null) ? (string) $domain : null;
            $domain = ($parameters !== null) ? (string) (is_array($parameters) ? reset($parameters) : $parameters) : null;
            $parameters = $count;
            $count = null;
        }

        $this->count = $count !== null ? (int) $count : null;
        $this->parameters = (array) $parameters;
        $this->domain = $domain;
        $this->locale = $locale;
    }

    /**
     * @param Translator $translator
     * @param int|NULL $count
     * @param array<string|int,string> $parameters
     * @param string|NULL $domain
     * @param string|NULL $locale
     * @return string
     */
    public function translate(Translator $translator, $count = null, array $parameters = [], $domain = null, $locale = null): string
    {
        if (!is_string($this->message)) {
            throw new InvalidStateException('Message is not a string, type ' . gettype($this->message) . ' given.');
        }

        $count = ($count !== null) ? (int) $count : $this->count;
        $parameters = !empty($parameters) ? $parameters : $this->parameters;
        $domain = ($domain !== null) ? $domain : $this->domain;
        $locale = ($locale !== null) ? $locale : $this->locale;

        return $translator->translate($this->message, $count, (array) $parameters, $domain, $locale);
    }

    /**
     * @internal
     * @param Translator $translator
     */
    public function setTranslator(Translator $translator): void
    {
        $this->translator = $translator;
    }

    public function __toString()
    {
        if ($this->translator === null) {
            return $this->message;
        }

        try {
            return (string) $this->translate($this->translator);
        } catch (Throwable $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);
        }
    }

    public function __sleep()
    {
        $this->translator = null;
        return ['message', 'count', 'parameters', 'domain', 'locale'];
    }

}
