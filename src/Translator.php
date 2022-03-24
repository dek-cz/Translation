<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Translation;

use Kdyby\Translation\Diagnostics\Panel;
use Latte\Runtime\IHtmlString as LatteHtmlString;
use Nette\Utils\IHtmlString as NetteHtmlString;
use Nette\Utils\Strings;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Formatter\ChoiceMessageFormatterInterface;
use Symfony\Component\Translation\Formatter\MessageFormatterInterface;
use Symfony\Component\Translation\Loader\LoaderInterface;

class Translator extends \Symfony\Component\Translation\Translator implements \Kdyby\Translation\ITranslator
{

    use \Nette\SmartObject;

    /**
     * @var \Kdyby\Translation\IUserLocaleResolver
     */
    private $localeResolver;

    /**
     * @var \Kdyby\Translation\CatalogueCompiler
     */
    private $catalogueCompiler;

    /**
     * @var \Kdyby\Translation\FallbackResolver
     */
    private $fallbackResolver;

    /**
     * @var \Kdyby\Translation\IResourceLoader
     */
    private $translationsLoader;

    /**
     * @var \Psr\Log\LoggerInterface|NULL
     */
    private $psrLogger;

    /**
     * @var \Kdyby\Translation\Diagnostics\Panel|NULL
     */
    private $panel;

    /**
     * @var array<string,bool>
     */
    private $availableResourceLocales = [];

    /**
     * @var string
     */
    private $defaultLocale;

    /**
     * @var string|NULL
     */
    private $localeWhitelist;

    /**
     * @var \Symfony\Component\Translation\Formatter\MessageFormatterInterface
     */
    private $formatter;

    /**
     * @param \Kdyby\Translation\IUserLocaleResolver $localeResolver
     * @param \Symfony\Component\Translation\Formatter\MessageFormatterInterface $formatter
     * @param \Kdyby\Translation\CatalogueCompiler $catalogueCompiler
     * @param \Kdyby\Translation\FallbackResolver $fallbackResolver
     * @param \Kdyby\Translation\IResourceLoader $loader
     * @throws \InvalidArgumentException
     */
    public function __construct(
        IUserLocaleResolver $localeResolver,
        MessageFormatterInterface $formatter,
        CatalogueCompiler $catalogueCompiler,
        FallbackResolver $fallbackResolver,
        IResourceLoader $loader
    )
    {
        $this->localeResolver = $localeResolver;
        $this->formatter = $formatter;
        $this->catalogueCompiler = $catalogueCompiler;
        $this->fallbackResolver = $fallbackResolver;
        $this->translationsLoader = $loader;

        parent::__construct('', $formatter);
    }

    /**
     * @internal
     * @param \Kdyby\Translation\Diagnostics\Panel $panel
     */
    public function injectPanel(Panel $panel): void
    {
        $this->panel = $panel;
    }

    /**
     * @param \Psr\Log\LoggerInterface|NULL $logger
     */
    public function injectPsrLogger(LoggerInterface $logger = NULL): void
    {
        $this->psrLogger = $logger;
    }

    /**
     * Translates the given string.
     *
     * @param string|\Kdyby\Translation\Phrase|mixed $message The message id
     * @param array<string|int,string>|string|int|NULL ...$arg An array of parameters for the message
     * @throws \InvalidArgumentException
     * @return string
     */
    public function translate($message, ...$arg): string
    {
        if ($message instanceof Phrase) {
            return $message->translate($this);
        }

        $count = isset($arg[0]) ? $arg[0] : NULL;
        $parameters = isset($arg[1]) ? $arg[1] : [];
        $domain = isset($arg[2]) ? $arg[2] : NULL;
        $locale = isset($arg[3]) ? $arg[3] : NULL;

        if (is_array($count)) {
            $locale = ($domain !== NULL) ? (string) (is_array($domain) ? reset($domain) : $domain) : NULL;
            $domain = ($parameters !== NULL && !empty($parameters)) ? (string) (is_array($parameters) ? reset($parameters) : $parameters) : NULL;
            $parameters = $count;
            $count = NULL;
        }

        if (empty($message)) {
            return '';
        } elseif ($message instanceof NetteHtmlString || $message instanceof LatteHtmlString) {
            $this->logMissingTranslation($message->__toString(), (string) (is_array($domain) ? reset($domain) : $domain), (string) (is_array($locale) ? reset($locale) : $locale));
            return (string) $message; // what now?
        } elseif (is_int($message)) {
            $message = (string) $message;
        }

        if (!is_string($message)) {
            throw new \Kdyby\Translation\InvalidArgumentException(sprintf('Message id must be a string, %s was given', gettype($message)));
        }

        if (Strings::startsWith($message, '//')) {
            if ($domain !== NULL) {
                throw new \Kdyby\Translation\InvalidArgumentException(sprintf(
                            'Providing domain "%s" while also having the message "%s" absolute is not supported',
                            (is_array($domain) ? reset($domain) : $domain),
                            $message
                ));
            }

            $message = Strings::substring($message, 2);
        }
        $parameters = is_array($parameters) ? $parameters : [];
        $tmp = [];
        foreach ($parameters as $key => $val) {
            $tmp['%' . trim((string) $key, '%') . '%'] = $val;
        }
        $parameters = $tmp;

        if ($count !== NULL && is_scalar($count)) {
            return $this->transChoice($message, (int) $count, $parameters + ['%count%' => (string) $count], (is_string($domain) || is_null($domain) ? $domain : null), (is_string($locale) || is_null($locale) ? $locale : null));
        }

        return $this->trans($message, $parameters, (is_string($domain) || is_null($domain) ? $domain : null), (is_string($locale) || is_null($locale) ? $locale : null));
    }

    /**
     * {@inheritdoc}
     * @param int|string $message
     * @param array<string|int,string> $parameters
     * @param ?string $domain
     * @param ?string $locale
     */
    public function trans($message, array $parameters = [], $domain = NULL, $locale = NULL): string
    {
        if (is_int($message)) {
            $message = (string) $message;
        }

        if (!is_string($message)) {
            throw new \Kdyby\Translation\InvalidArgumentException(sprintf('Message id must be a string, %s was given', gettype($message)));
        }

        if ($domain === NULL) {
            list($domain, $id) = $this->extractMessageDomain($message);
        } else {
            $id = $message;
        }

        $result = parent::trans($id, $parameters, $domain, $locale);
        if ($result === "\x01") {
            $this->logMissingTranslation($message, $domain, $locale);
            $result = strtr($message, $parameters);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     * @param int|string $message
     * @param array<string|int,string> $parameters
     * @param ?string $domain
     * @param ?string $locale
     */
    public function transChoice($message, $number, array $parameters = [], $domain = NULL, $locale = NULL): string
    {
        if (is_int($message)) {
            $message = (string) $message;
        }

        if (!is_string($message)) {
            throw new \Kdyby\Translation\InvalidArgumentException(sprintf('Message id must be a string, %s was given', gettype($message)));
        }

        if ($domain === NULL) {
            list($domain, $id) = $this->extractMessageDomain($message);
        } else {
            $id = $message;
        }

        try {
            $result = parent::transChoice($id, $number, $parameters, $domain, $locale);
        } catch (\Exception $e) {
            $result = $id;
            if ($this->panel !== NULL) {
                $this->panel->choiceError($e, $domain);
            }
        }

        if ($result === "\x01") {
            $this->logMissingTranslation($message, $domain, $locale);
            if ($locale === NULL) {
                $locale = $this->getLocale();
            }
            if ($locale === NULL) {
                $result = strtr($message, $parameters);
            } else {
                if (!$this->formatter instanceof ChoiceMessageFormatterInterface) {
                    $result = $id;
                    if ($this->panel !== NULL) {
                        $this->panel->choiceError(new \Symfony\Component\Translation\Exception\LogicException(sprintf('The formatter "%s" does not support plural translations.', get_class($this->formatter))), $domain);
                    }
                } else {
                    $result = $this->formatter->choiceFormat($message, (int) $number, $locale, $parameters);
                }
            }
        }

        return $result;
    }

    /**
     * @param string $format
     * @param \Symfony\Component\Translation\Loader\LoaderInterface $loader
     */
    public function addLoader($format, LoaderInterface $loader): void
    {
        parent::addLoader($format, $loader);
        $this->translationsLoader->addLoader($format, $loader);
    }

    /**
     * @return \Symfony\Component\Translation\Loader\LoaderInterface[]
     */
    protected function getLoaders()
    {
        return $this->translationsLoader->getLoaders();
    }

    /**
     * @param array<string> $whitelist
     */
    public function setLocaleWhitelist(array $whitelist = NULL): void
    {
        $this->localeWhitelist = self::buildWhitelistRegexp($whitelist);
    }

    /**
     * {@inheritdoc}
     * @param string|NULL $format
     * @param string $resource
     * @param string|NULL $locale
     * @param string|NULL $domain
     */
    public function addResource($format, $resource, $locale, $domain = NULL): void
    {
        if ($this->localeWhitelist !== NULL && !preg_match($this->localeWhitelist, $locale ?? '')) {
            if ($this->panel !== NULL) {
                $this->panel->addIgnoredResource($format, $resource, $locale ?? '', $domain);
            }
            return;
        }

        parent::addResource($format ?? '', $resource, $locale ?? '', $domain);
        $this->catalogueCompiler->addResource($format ?? '', $resource, $locale ?? '', $domain);
        $this->availableResourceLocales[$locale] = TRUE;

        if ($this->panel !== NULL) {
            $this->panel->addResource($format, $resource, $locale ?? '', $domain);
        }
    }

    /**
     * {@inheritdoc}
     * @param array<string> $locales
     */
    public function setFallbackLocales(array $locales): void
    {
        parent::setFallbackLocales($locales);
        $this->fallbackResolver->setFallbackLocales($locales);
    }

    /**
     * Returns array of locales from given resources
     *
     * @return array<string>
     */
    public function getAvailableLocales()
    {
        $locales = array_keys($this->availableResourceLocales);
        sort($locales);
        return $locales;
    }

    /**
     * Sets the current locale.
     *
     * @param string|NULL $locale The locale
     * @throws \InvalidArgumentException If the locale contains invalid characters
     */
    public function setLocale($locale): void
    {
        parent::setLocale($locale ?? '');
    }

    /**
     * Returns the current locale.
     *
     * @return string|NULL The locale
     */
    public function getLocale()
    {
        if (empty(parent::getLocale()) || (class_exists(\Locale::class) && parent::getLocale() === \Locale::getDefault())) {
            $this->setLocale($this->localeResolver->resolve($this));
        }

        return parent::getLocale();
    }

    /**
     * @return string
     */
    public function getDefaultLocale()
    {
        return $this->defaultLocale;
    }

    /**
     * @param string $locale
     * @return \Kdyby\Translation\Translator
     */
    public function setDefaultLocale($locale): self
    {
        $this->assertValidLocale($locale);
        $this->defaultLocale = $locale;
        return $this;
    }

    /**
     * @param string $messagePrefix
     * @return \Kdyby\Translation\ITranslator
     */
    public function domain($messagePrefix)
    {
        return new PrefixedTranslator($messagePrefix, $this);
    }

    /**
     * @return \Kdyby\Translation\TemplateHelpers
     */
    public function createTemplateHelpers()
    {
        return new TemplateHelpers($this);
    }

    /**
     * {@inheritdoc}
     * @param string $locale
     */
    protected function loadCatalogue($locale): void
    {
        if (empty($locale)) {
            throw new \Kdyby\Translation\InvalidArgumentException('Invalid locale.');
        }

        if (isset($this->catalogues[$locale])) {
            return;
        }

        $this->catalogues = $this->catalogueCompiler->compile($this, $this->catalogues, $locale);
    }

    /**
     * {@inheritdoc}
     * @param string $locale
     * @return array<string>
     */
    protected function computeFallbackLocales($locale): array
    {
        return $this->fallbackResolver->compute($this, $locale);
    }

    /**
     * Asserts that the locale is valid, throws an Exception if not.
     *
     * @param string $locale Locale to tests
     * @throws \InvalidArgumentException If the locale contains invalid characters
     */
    protected function assertValidLocale($locale): void
    {
        if (preg_match('~^[a-z0-9@_\\.\\-]*\z~i', $locale) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid "%s" locale.', $locale));
        }
    }

    /**
     * @param string $message
     * @return array<string|int,string>
     */
    private function extractMessageDomain($message): array
    {
        if (strpos(substr($message, 0, -1), '.') !== FALSE && strpos($message, ' ') === FALSE) {
            list($domain, $message) = explode('.', $message, 2);
        } else {
            $domain = 'messages';
        }

        return [$domain, $message];
    }

    /**
     * @param string|NULL $message
     * @param string|NULL $domain
     * @param string|NULL $locale
     */
    protected function logMissingTranslation($message, $domain, $locale): void
    {
        if ($message === NULL) {
            return;
        }

        if ($this->psrLogger !== NULL) {
            $this->psrLogger->notice('Missing translation', [
                'message' => $message,
                'domain' => $domain,
                'locale' => $locale ?: $this->getLocale(),
            ]);
        }

        if ($this->panel !== NULL) {
            $this->panel->markUntranslated($message, $domain);
        }
    }

    /**
     * @param array<string>|NULL $whitelist
     * @return null|string
     */
    public static function buildWhitelistRegexp($whitelist)
    {
        return ($whitelist !== NULL) ? '~^(' . implode('|', $whitelist) . ')~i' : NULL;
    }

}
