<?php declare(strict_types = 1);

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Translation\Extractors;

use Latte\MacroTokens;
use Latte\Parser;
use Latte\PhpWriter;
use Nette\SmartObject;
use Nette\Utils\Finder;
use SplFileInfo;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Traversable;

class LatteExtractor implements ExtractorInterface
{

    use SmartObject;

    /** @var string */
    private $prefix;

    /**
     * {@inheritDoc}
     */
    public function extract($directory, MessageCatalogue $catalogue): void
    {
        /** @var string|array<string> $dic*/
        $dic = is_string($directory) ? $directory : ($directory instanceof Traversable ? iterator_to_array($directory) : (array) $directory);
        foreach (Finder::findFiles('*.latte', '*.phtml')->from($dic) as $file) {
            $this->extractFile($file, $catalogue);
        }
    }

    /**
     * Extracts translation messages from a file to the catalogue.
     *
     * @param SplFileInfo|string $file The path to look into
     * @param MessageCatalogue $catalogue The catalogue
     */
    public function extractFile(SplFileInfo|string $file, MessageCatalogue $catalogue): void
    {
        $buffer = null;
        $parser = new Parser();
        if ($file instanceof SplFileInfo) {
            $file = $file->getPathname();
        }

        $tmp = file_get_contents($file ?: '');
        foreach ($tokens = $parser->parse($tmp ?: '') as $token) {
            if ($token->type !== $token::MACRO_TAG || !in_array($token->name, ['_', '/_'], true)) {
                if ($buffer !== null) {
                    $buffer .= $token->text;
                }

                continue;
            }

            if ($token->name === '/_' || ($token->name === '_' && $token->closing === true)) {
                if ($buffer !== null) {
                    $catalogue->set(($this->prefix ? $this->prefix . '.' : '') . $buffer, $buffer);
                    $buffer = null;
                }
            } elseif ($token->name === '_' && empty($token->value)) {
                $buffer = '';
            } else {
                $args = new MacroTokens($token->value);
                $writer = new PhpWriter($args, $token->modifiers);

                $message = $writer->write('%node.word');
                if (in_array(substr(trim($message), 0, 1), ['"', '\''], true)) {
                    $message = substr(trim($message), 1, -1);
                }

                $catalogue->set(($this->prefix ? $this->prefix . '.' : '') . $message, $message);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function setPrefix($prefix): void
    {
        $this->prefix = $prefix;
    }

}
