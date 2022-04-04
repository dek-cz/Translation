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

use Nette\SmartObject;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Symfony\Component\Translation\MessageCatalogueInterface;

class CatalogueFactory
{

    use SmartObject;

    /** @var FallbackResolver */
    private $fallbackResolver;

    /** @var IResourceLoader */
    private $loader;

    /** @var array <string, array<int, array<int, string>|string>> */
    private $resources = [];

    public function __construct(FallbackResolver $fallbackResolver, IResourceLoader $loader)
    {
        $this->fallbackResolver = $fallbackResolver;
        $this->loader = $loader;
    }

    /**
     * {@inheritdoc}
     */
    public function addResource(string $format, string $resource, string $locale, ?string $domain): void
    {
        /** @var string $domain */
        $domain = $domain ?: 'messages';
        $this->resources[$locale][] = [$format, $resource, $domain];
    }

    /**
     * @return array<int,string>
     */
    public function getResources(): array
    {
        $list = [];
        foreach (array_values($this->resources) as $resources) {
            foreach ($resources as $meta) {
                $list[] = $meta[1]; // resource file
            }
        }

        return $list;
    }

    /**
     * @param Translator $translator
     * @param MessageCatalogueInterface[] $availableCatalogues
     * @param string $locale
     * @throws NotFoundResourceException
     * @return MessageCatalogueInterface
     */
    public function createCatalogue(Translator $translator, array &$availableCatalogues, $locale): MessageCatalogueInterface
    {
        try {
            $this->doLoadCatalogue($availableCatalogues, $locale);
        } catch (NotFoundResourceException $e) {
            if (!$this->fallbackResolver->compute($translator, $locale)) {
                throw $e;
            }
        }

        $current = $availableCatalogues[$locale];

        $chain = [$locale => true];
        foreach ($this->fallbackResolver->compute($translator, $locale) as $fallback) {
            if (!isset($availableCatalogues[$fallback])) {
                $this->doLoadCatalogue($availableCatalogues, $fallback);
            }

            $newFallback = $availableCatalogues[$fallback];
            $newFallbackFallback = $newFallback->getFallbackCatalogue();
            if ($newFallbackFallback !== null && isset($chain[$newFallbackFallback->getLocale()])) {
                break;
            }

            $current->addFallbackCatalogue($newFallback);
            $current = $newFallback;
            $chain[$fallback] = true;
        }

        return $availableCatalogues[$locale];
    }

    /**
     * @param array<string, MessageCatalogue> $availableCatalogues
     * @param ?string $locale
     * @return MessageCatalogue
     */
    private function doLoadCatalogue(array &$availableCatalogues, ?string $locale): MessageCatalogue
    {
        $availableCatalogues[$locale] = $catalogue = new MessageCatalogue($locale);

        if (isset($this->resources[$locale])) {
            foreach ($this->resources[$locale] as $resource) {
                $this->loader->loadResource($resource[0], $resource[1], $resource[2], $catalogue);
            }
        }

        return $catalogue;
    }

}
