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

use Kdyby\Translation\Diagnostics\Panel;
use Latte\Runtime\IHtmlString as LatteHtmlString;
use Locale;
use Nette\SmartObject;
use Nette\Utils\IHtmlString as NetteHtmlString;
use Nette\Utils\Strings;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Exception\LogicException;
use Symfony\Component\Translation\Formatter\ChoiceMessageFormatterInterface;
use Symfony\Component\Translation\Formatter\MessageFormatterInterface;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Throwable;

class Translator extends \Symfony\Component\Translation\Translator implements ITranslator
{

    use SmartObject;

    /** @var IUserLocaleResolver */
    private $localeResolver;

    /** @var CatalogueCompiler */
    private $catalogueCompiler;

    /** @var FallbackResolver */
    private $fallbackResolver;

    /** @var IResourceLoader */
    private $translationsLoader;

    /** @var LoggerInterface|NULL */
    private $psrLogger;

    /** @var Panel|NULL */
    private $panel;

    /** @var array<string,bool> */
    private $availableResourceLocales = [];

    /** @var string */
    private $defaultLocale;

    /** @var string|NULL */
    private $localeWhitelist;

    /** @var MessageFormatterInterface */
    private $formatter;

    /**
     * @param IUserLocaleResolver $localeResolver
     * @param MessageFormatterInterface $formatter
     * @param CatalogueCompiler $catalogueCompiler
     * @param FallbackResolver $fallbackResolver
     * @param IResourceLoader $loader
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
     * @param Panel $panel
     */
    public function injectPanel(Panel $panel): void
    {
        $this->panel = $panel;
    }

    /**
     * @param LoggerInterface|NULL $logger
     */
    public function injectPsrLogger(?LoggerInterface $logger = null): void
    {
        $this->psrLogger = $logger;
    }

    /**
     * Translates the given string.
     *
     * @param string|Phrase|mixed $message The message id
     * @param array<string|int,string>|string|int|NULL ...$arg An array of parameters for the message
     * @throws \InvalidArgumentException
     * @return string
     */
    public function translate($message, ...$arg): string
    {
        if ($message instanceof Phrase) {
            return $message->translate($this);
        }

        $count = $arg[0] ?? null;
        $parameters = $arg[1] ?? [];
        $domain = $arg[2] ?? null;
        $locale = $arg[3] ?? null;

        if (is_array($count)) {
            $locale = ($domain !== null) ? (string) (is_array($domain) ? reset($domain) : $domain) : null;
            $domain = ($parameters !== null && !empty($parameters)) ? (string) (is_array($parameters) ? reset($parameters) : $parameters) : null;
            $parameters = $count;
            $count = null;
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
            throw new InvalidArgumentException(sprintf('Message id must be a string, %s was given', gettype($message)));
        }

        if (Strings::startsWith($message, '//')) {
            if ($domain !== null) {
                throw new InvalidArgumentException(sprintf('Providing domain "%s" while also having the message "%s" absolute is not supported', (is_array($domain) ? reset($domain) : $domain), $message));
            }

            $message = Strings::substring($message, 2);
        }

        $parameters = is_array($parameters) ? $parameters : [];
        $tmp = [];
        foreach ($parameters as $key => $val) {
            $tmp['%' . trim((string) $key, '%') . '%'] = $val;
        }

        $parameters = $tmp;

        if ($count !== null && is_scalar($count)) {
            return $this->transChoice($message, (int) $count, $parameters + ['%count%' => (string) $count], (is_string($domain) || $domain === null ? $domain : null), (is_string($locale) || $locale === null ? $locale : null));
        }

        return $this->trans($message, $parameters, (is_string($domain) || $domain === null ? $domain : null), (is_string($locale) || $locale === null ? $locale : null));
    }

    /**
     * {@inheritdoc}
     *
     * @param int|string $message
     * @param array<string|int,string> $parameters
     * @param ?string $domain
     * @param ?string $locale
     */
    public function trans($message, array $parameters = [], $domain = null, $locale = null): string
    {
        if (is_int($message)) {
            $message = (string) $message;
        }

        if (!is_string($message)) {
            throw new InvalidArgumentException(sprintf('Message id must be a string, %s was given', gettype($message)));
        }

        if ($domain === null) {
            [$domain, $id] = $this->extractMessageDomain($message);
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
     *
     * @param int|string $message
     * @param array<string|int,string> $parameters
     * @param ?string $domain
     * @param ?string $locale
     */
    public function transChoice($message, $number, array $parameters = [], $domain = null, $locale = null): string
    {
        if (is_int($message)) {
            $message = (string) $message;
        }

        if (!is_string($message)) {
            throw new InvalidArgumentException(sprintf('Message id must be a string, %s was given', gettype($message)));
        }

        if ($domain === null) {
            [$domain, $id] = $this->extractMessageDomain($message);
        } else {
            $id = $message;
        }

        try {
            $result = parent::transChoice($id, $number, $parameters, $domain, $locale);
        } catch (Throwable $e) {
            $result = $id;
            if ($this->panel !== null) {
                $this->panel->choiceError($e, $domain);
            }
        }

        if ($result === "\x01") {
            $this->logMissingTranslation($message, $domain, $locale);
            if ($locale === null) {
                $locale = $this->getLocale();
            }

            if ($locale === null) {
                $result = strtr($message, $parameters);
            } else {
                if (!$this->formatter instanceof ChoiceMessageFormatterInterface) {
                    $result = $id;
                    if ($this->panel !== null) {
                        $this->panel->choiceError(new LogicException(sprintf('The formatter "%s" does not support plural translations.', get_class($this->formatter))), $domain);
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
     * @param LoaderInterface $loader
     */
    public function addLoader($format, LoaderInterface $loader): void
    {
        parent::addLoader($format, $loader);
        $this->translationsLoader->addLoader($format, $loader);
    }

    /**
     * @return LoaderInterface[]
     */
    protected function getLoaders()
    {
        return $this->translationsLoader->getLoaders();
    }

    /**
     * @param array<string> $whitelist
     */
    public function setLocaleWhitelist(?array $whitelist = null): void
    {
        $this->localeWhitelist = self::buildWhitelistRegexp($whitelist);
    }

    /**
     * {@inheritdoc}
     *
     * @param string|NULL $format
     * @param string $resource
     * @param string|NULL $locale
     * @param string|NULL $domain
     */
    public function addResource($format, $resource, $locale, $domain = null): void
    {
        if ($this->localeWhitelist !== null && !preg_match($this->localeWhitelist, $locale ?? '')) {
            if ($this->panel !== null) {
                $this->panel->addIgnoredResource($format, $resource, $locale ?? '', $domain);
            }

            return;
        }

        parent::addResource($format ?? '', $resource, $locale ?? '', $domain);
        $this->catalogueCompiler->addResource($format ?? '', $resource, $locale ?? '', $domain);
        $this->availableResourceLocales[$locale] = true;

        if ($this->panel !== null) {
            $this->panel->addResource($format, $resource, $locale ?? '', $domain);
        }
    }

    /**
     * {@inheritdoc}
     *
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
        if (empty(parent::getLocale()) || (class_exists(Locale::class) && parent::getLocale() === Locale::getDefault())) {
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
     * @return Translator
     */
    public function setDefaultLocale($locale): self
    {
        $this->assertValidLocale($locale);
        $this->defaultLocale = $locale;
        return $this;
    }

    /**
     * @param string $messagePrefix
     * @return ITranslator
     */
    public function domain($messagePrefix)
    {
        return new PrefixedTranslator($messagePrefix, $this);
    }

    /**
     * @return TemplateHelpers
     */
    public function createTemplateHelpers()
    {
        return new TemplateHelpers($this);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $locale
     */
    protected function loadCatalogue($locale): void
    {
        if (empty($locale)) {
            throw new InvalidArgumentException('Invalid locale.');
        }

        if (isset($this->catalogues[$locale])) {
            return;
        }

        $this->catalogues = $this->catalogueCompiler->compile($this, $this->catalogues, $locale);
    }

    /**
     * {@inheritdoc}
     *
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
        if (strpos(substr($message, 0, -1), '.') !== false && strpos($message, ' ') === false) {
            [$domain, $message] = explode('.', $message, 2);
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
        if ($message === null) {
            return;
        }

        if ($this->psrLogger !== null) {
            $this->psrLogger->notice('Missing translation', [
                'message' => $message,
                'domain' => $domain,
                'locale' => $locale ?: $this->getLocale(),
            ]);
        }

        if ($this->panel !== null) {
            $this->panel->markUntranslated($message, $domain);
        }
    }

    /**
     * @param array<string>|NULL $whitelist
     * @return string|null
     */
    public static function buildWhitelistRegexp($whitelist)
    {
        return ($whitelist !== null) ? '~^(' . implode('|', $whitelist) . ')~i' : null;
    }

}
