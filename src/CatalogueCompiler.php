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

use Kdyby\Translation\Caching\PhpFileStorage;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\Caching\Storages\MemoryStorage;
use Nette\PhpGenerator\Helpers as GeneratorHelpers;
use Nette\PhpGenerator\PhpLiteral;
use Nette\SmartObject;
use Symfony\Component\Translation\MessageCatalogueInterface;

class CatalogueCompiler
{

    use SmartObject;

    /** @var Cache */
    private $cache;

    /** @var FallbackResolver */
    private $fallbackResolver;

    /** @var CatalogueFactory */
    private $catalogueFactory;

    public function __construct(
        IStorage $cacheStorage,
        FallbackResolver $fallbackResolver,
        CatalogueFactory $catalogueFactory
    )
    {
        $this->cache = new Cache($cacheStorage, Translator::class);
        $this->fallbackResolver = $fallbackResolver;
        $this->catalogueFactory = $catalogueFactory;
    }

    /**
     * Replaces cache storage with simple memory storage (per-request).
     */
    public function enableDebugMode(): void
    {
        $this->cache = new Cache(new MemoryStorage());
    }

    public function invalidateCache(): void
    {
        $this->cache->clean([Cache::ALL => true]);
    }

    /**
     * @param string $format
     * @param string $resource
     * @param string $locale
     * @param ?string $domain
     */
    public function addResource(string $format, string $resource, string $locale, ?string $domain = null): void
    {
        $this->catalogueFactory->addResource($format, $resource, $locale, $domain);
    }

    /**
     * @param Translator $translator
     * @param MessageCatalogueInterface[] $availableCatalogues
     * @param string $locale
     * @throws InvalidArgumentException
     * @return MessageCatalogueInterface[]
     */
    public function compile(Translator $translator, array &$availableCatalogues, $locale)
    {
        if (empty($locale)) {
            throw new InvalidArgumentException('Invalid locale');
        }

        if (isset($availableCatalogues[$locale])) {
            return $availableCatalogues;
        }

        $cacheKey = [$locale, $translator->getFallbackLocales()];

        $storage = $this->cache->getStorage();
        if (!$storage instanceof PhpFileStorage) {
            /** @var ?array<string,string> $messages */
            $messages = $this->cache->load($cacheKey);
            if ($messages !== null) {
                $availableCatalogues[$locale] = new MessageCatalogue($locale, $messages);
                return $availableCatalogues;
            }

            $this->catalogueFactory->createCatalogue($translator, $availableCatalogues, $locale);
            $this->cache->save($cacheKey, $availableCatalogues[$locale]->all());
            return $availableCatalogues;
        }

        $storage->hint = $locale;
        /** @var ?array<string,string> $cached */
        $cached = $compiled = $this->cache->load($cacheKey);
        if ($compiled === null) {
            $this->catalogueFactory->createCatalogue($translator, $availableCatalogues, $locale);
            $this->cache->save($cacheKey, $compiled = $this->compilePhpCache($translator, $availableCatalogues, $locale));
            /** @var array<string,string> $cached */
            $cached = $this->cache->load($cacheKey);
        }

        if (isset($cached['file'])) {
            $availableCatalogues[$locale] = self::load($cached['file']);
        }

        return $availableCatalogues;
    }

    /**
     * @param Translator $translator
     * @param MessageCatalogueInterface[] $availableCatalogues
     * @param string $locale
     * @return string
     */
    protected function compilePhpCache(Translator $translator, array &$availableCatalogues, $locale)
    {
        $fallbackContent = '';
        $current = new PhpLiteral('');
        foreach ($this->fallbackResolver->compute($translator, $locale) as $fallback) {
            $fback = preg_replace('~[^a-z0-9_]~i', '_', $fallback) ?: '';
            $fallbackSuffix = new PhpLiteral(ucfirst($fback));

            $fallbackContent .= GeneratorHelpers::format('
$catalogue? = new MessageCatalogue(?, ?);
$catalogue?->addFallbackCatalogue($catalogue?);

', $fallbackSuffix, $fallback, $availableCatalogues[$fallback]->all(), $current, $fallbackSuffix);
            $current = $fallbackSuffix;
        }

        $content = GeneratorHelpers::format('
use Kdyby\Translation\MessageCatalogue;

$catalogue = new MessageCatalogue(?, ?);

?
return $catalogue;

', $locale, $availableCatalogues[$locale]->all(), new PhpLiteral($fallbackContent));

        return '<?php' . "\n\n" . $content;
    }

    /**
     * @return MessageCatalogueInterface
     */
    protected static function load()
    {
        /**
         * Ugly hack because of BC break in Nette\Caching\Storages due to this commit:
         * https://github.com/nette/caching/commit/0e5d0699a82a9a25b3daffd832c04b1521544770
         * FileStorage no longer escapes the meta head with <?php, which causes Kdyby/Translator
         * to print the cache meta head with every translate() call.
         * */
        ob_start();
        $fnc = include func_get_arg(0);
        ob_get_clean();
        return $fnc;
    }

}
