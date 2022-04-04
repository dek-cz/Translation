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

use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;

interface IResourceLoader
{

    /**
     * Adds a loader to the translation extractor.
     *
     * @param string $format The format of the loader
     * @param LoaderInterface $loader
     */
    public function addLoader($format, LoaderInterface $loader): void;

    /**
     * @return array<LoaderInterface>
     */
    public function getLoaders(): array;

    /**
     * @param string $format
     * @param string $resource
     * @param string $domain
     * @param MessageCatalogue $catalogue
     * @throws LoaderNotFoundException
     */
    public function loadResource($format, $resource, $domain, MessageCatalogue $catalogue): void;

}
