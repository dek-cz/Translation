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

interface IUserLocaleResolver
{

    /**
     * @param Translator $translator
     * @return string|NULL
     */
    public function resolve(Translator $translator);

}
