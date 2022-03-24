<?php declare(strict_types = 1);

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Translation;

use Nette\DI\Container as DIContainer;
use Nette\SmartObject;
use Nette\Utils\Finder;
use SplFileInfo;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * TranslationLoader loads translation messages from translation files.
 */
class TranslationLoader implements IResourceLoader
{

    use SmartObject;

    /**
     * Loaders used for import.
     *
     * @var array|LoaderInterface[]
     */
    private $loaders = [];

    /** @var array<string,string> */
    private $serviceIds = [];

    /** @var DIContainer */
    private $serviceLocator;

    /**
     * @internal
     * @param array<string,string> $serviceIds
     * @param DIContainer $serviceLocator
     */
    public function injectServiceIds(array $serviceIds, DIContainer $serviceLocator): void
    {
        $this->serviceIds = $serviceIds;
        $this->serviceLocator = $serviceLocator;
    }

    /**
     * Adds a loader to the translation extractor.
     *
     * @param string $format The format of the loader
     * @param LoaderInterface $loader
     */
    public function addLoader($format, LoaderInterface $loader): void
    {
        $this->loaders[$format] = $loader;
    }

    /**
     * @return array<LoaderInterface>
     */
    public function getLoaders(): array
    {
        foreach ($this->serviceIds as $format => $loaderId) {
            $this->loaders[$format] = $this->serviceLocator->getService($loaderId);
        }

        $this->serviceIds = [];

        return $this->loaders;
    }

    /**
     * Loads translation messages from a directory to the catalogue.
     *
     * @param string $directory the directory to look into
     * @param MessageCatalogue $catalogue the catalogue
     */
    public function loadMessages($directory, MessageCatalogue $catalogue): void
    {
        foreach ($this->getLoaders() as $format => $loader) {
            // load any existing translation files
            $extension = $catalogue->getLocale() . '.' . $format;
            foreach (Finder::findFiles('*.' . $extension)->from($directory) as $file) {
                /** @var SplFileInfo $file */
                $domain = substr($file->getFileName(), 0, -1 * strlen($extension) - 1);
                $this->loadResource($format, $file->getPathname(), $domain, $catalogue);
            }
        }
    }

    /**
     * @param string $format
     * @param string $resource
     * @param string $domain
     * @param MessageCatalogue $catalogue
     * @throws LoaderNotFoundException
     */
    public function loadResource($format, $resource, $domain, MessageCatalogue $catalogue): void
    {
        if (!isset($this->loaders[$format])) {
            if (!isset($this->serviceIds[$format])) {
                throw new LoaderNotFoundException(sprintf('The "%s" translation loader is not registered.', $resource[0]));
            }

            $this->loaders[$format] = $this->serviceLocator->getService($this->serviceIds[$format]);
            unset($this->serviceIds[$format]);
        }

        $catalogue->addCatalogue($this->loaders[$format]->load($resource, $catalogue->getLocale(), $domain));
    }

}
