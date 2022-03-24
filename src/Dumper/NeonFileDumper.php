<?php declare(strict_types = 1);

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Translation\Dumper;

use Nette\Neon\Neon;
use Nette\SmartObject;
use Symfony\Component\Translation\Dumper\FileDumper;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * Generates Neon files from a message catalogue.
 */
class NeonFileDumper extends FileDumper
{

    use SmartObject;

    /**
     * Transforms a domain of a message catalogue to its string representation.
     *
     * @param MessageCatalogue $messages
     * @param string $domain
     * @param array<string,string> $options
     * @return string representation
     */
    public function formatCatalogue(MessageCatalogue $messages, $domain, array $options = []): string
    {
        return Neon::encode($messages->all($domain), Neon::BLOCK);
    }

    /**
     * @param MessageCatalogue $messages
     * @param string $domain
     * @return string representation
     */
    protected function format(MessageCatalogue $messages, $domain): string
    {
        return Neon::encode($messages->all($domain), Neon::BLOCK);
    }

    /**
     * {@inheritDoc}
     */
    protected function getExtension(): string
    {
        return 'neon';
    }

}
