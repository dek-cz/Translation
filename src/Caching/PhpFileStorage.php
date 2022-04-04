<?php
declare(strict_types = 1);

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Translation\Caching;

use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\Caching\Storages\FileStorage;

/**
 * @internal
 */
class PhpFileStorage extends FileStorage implements IStorage
{

    /** @var string */
    public $hint;

    /**
     * Additional cache structure
     */
    private const FILE = 'file';
    private const HANDLE = 'handle';

    /**
     * Reads cache data from disk.
     *
     * @param array<string,string> $meta
     * @return array<string,string>
     */
    protected function readData(array $meta): array
    {
        return [
            'file' => $meta[self::FILE],
            'handle' => $meta[self::HANDLE],
        ];
    }

    /**
     * Returns file name.
     *
     * @param string $key
     * @return string
     */
    protected function getCacheFile(string $key): string
    {
        $cacheKey = substr_replace(
            $key,
            trim(strtr($this->hint, '\\/@', '.._'), '.') . '-',
            strpos($key, Cache::NAMESPACE_SEPARATOR) + 1,
            0
        );

        return parent::getCacheFile($cacheKey) . '.php';
    }

}
