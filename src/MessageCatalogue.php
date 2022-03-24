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

class MessageCatalogue extends \Symfony\Component\Translation\MessageCatalogue
{

    use SmartObject;

    /**
     * {@inheritdoc}
     */
    public function get($id, $domain = 'messages')
    {
        if ($this->defines($id, $domain)) {
            return parent::get($id, $domain);
        }

        if ($this->getFallbackCatalogue() !== null) {
            return $this->getFallbackCatalogue()->get($id, $domain);
        }

        return "\x01";
    }

}
