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
use Nette\Http\IRequest;
use Nette\SmartObject;

class AcceptHeaderResolver implements IUserLocaleResolver
{

    use SmartObject;

    public const ACCEPT_LANGUAGE_HEADER = 'Accept-Language';

    /** @var IRequest */
    private $httpRequest;

    /**
     * @param IRequest $httpRequest
     */
    public function __construct(IRequest $httpRequest)
    {
        $this->httpRequest = $httpRequest;
    }

    /**
     * Detects language from the Accept-Language header.
     * This method uses the code from Nette\Http\Request::detectLanguage.
     *
     * @see https://github.com/nette/http/blob/0d9ef49051fba799148ef877dd32928a68731766/src/Http/Request.php#L294-L326
     * @param Translator $translator
     * @return string|NULL
     */
    public function resolve(Translator $translator)
    {
        $header = $this->httpRequest->getHeader(self::ACCEPT_LANGUAGE_HEADER);
        if (!$header) {
            return null;
        }

        $langs = [];
        foreach ($translator->getAvailableLocales() as $locale) {
            $langs[] = $locale;
            if (strlen($locale) > 2) {
                $langs[] = substr($locale, 0, 2);
            }
        }

        if (!$langs) {
            return null;
        }

        $s = strtolower($header);  // case insensitive
        $s = strtr($s, '_', '-');  // cs_CZ means cs-CZ
        rsort($langs);             // first more specific
        preg_match_all('#(' . implode('|', $langs) . ')(?:-[^\s,;=]+)?\s*(?:;\s*q=([0-9.]+))?#', $s, $matches);

        if (!$matches[0]) {
            return null;
        }

        $max = 0;
        $lang = null;
        foreach ($matches[1] as $key => $value) {
            $q = $matches[2][$key] === '' ? 1.0 : (float) $matches[2][$key];
            if ($q > $max) {
                $max = $q;
                $lang = $value;
            }
        }

        return $lang;
    }

}
