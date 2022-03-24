<?php declare(strict_types = 1);

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Translation\LocaleResolver;

use Kdyby\Translation\IUserLocaleResolver;
use Kdyby\Translation\Translator;
use Nette\Http\IResponse;
use Nette\Http\Session;
use Nette\Http\SessionSection;
use Nette\SmartObject;
use stdClass;

/**
 * When you don't want to use the param resolver,
 * you simply won't use the parameter `locale` in router
 * and will implement a signal that will call `setLocale` on this class.
 *
 * When you set the locale to this resolver, it will be stored in session
 * and forced on all other requests of the visitor, because this resolver has the highest priority.
 *
 * Get this class using autowire, but beware, use only Kdyby\Translation\LocaleResolver\SessionResolver,
 * do not try to autowire Kdyby\Translation\IUserLocaleResolver, it will fail.
 */
class SessionResolver implements IUserLocaleResolver
{

    use SmartObject;

    /** @var SessionSection|stdClass */
    private $localeSession;

    /** @var IResponse */
    private $httpResponse;

    /** @var Session */
    private $session;

    public function __construct(Session $session, IResponse $httpResponse)
    {
        $this->localeSession = $session->getSection(static::class);
        $this->httpResponse = $httpResponse;
        $this->session = $session;
    }

    /**
     * @param string $locale
     */
    public function setLocale($locale = null): void
    {
        $this->localeSession->locale = $locale;
    }

    /**
     * @param Translator $translator
     * @return string|NULL
     */
    public function resolve(Translator $translator)
    {
        if (!$this->session->isStarted() && $this->httpResponse->isSent()) {
            trigger_error(
                'The advice of session locale resolver is required but the session has not been started and headers had been already sent. ' .
                'Either start your sessions earlier or disabled the SessionResolver.',
                E_USER_WARNING
            );
            return null;
        }

        if (empty($this->localeSession->locale)) {
            return null;
        }

        $short = array_map(function ($locale) {
            return substr($locale, 0, 2);
        }, $translator->getAvailableLocales());

        if (!in_array(substr($this->localeSession->locale, 0, 2), $short, true)) {
            return null;
        }

        return $this->localeSession->locale;
    }

}
