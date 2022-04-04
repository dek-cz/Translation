<?php
declare(strict_types = 1);

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
use Nette\Application\Application;
use Nette\Application\Request;
use Nette\SmartObject;

class LocaleParamResolver implements IUserLocaleResolver
{

    use SmartObject;

    /** @var Request */
    private $request;

    /** @var Translator */
    private $translator;

    public function setTranslator(Translator $translator): void
    {
        $this->translator = $translator;
    }

    /**
     * @param Application $sender
     * @param Request $request
     */
    public function onRequest(Application $sender, Request $request): void
    {
        $params = $request->getParameters();
        if ($request->getMethod() === Request::FORWARD && empty($params['locale'])) {
            return;
        }

        $this->request = $request;

        if (!$this->translator instanceof Translator) {
            return;
        }

        $this->translator->setLocale('');
        $this->translator->getLocale(); // invoke resolver
    }

    /**
     * @param Translator $translator
     * @return string|NULL
     */
    public function resolve(Translator $translator)
    {
        if ($this->request === null) {
            return null;
        }

        $params = $this->request->getParameters();
        return !empty($params['locale']) ? $params['locale'] : null;
    }

}
