<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Translation\Diagnostics;

use Kdyby\Translation\Translator;
use Nette\Application\Application;
use Nette\Application\Request;
use Nette\Reflection\ClassType;
use Nette\Utils\Arrays;
use Nette\Utils\Html;
use Nette\Utils\Strings;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Tracy\BlueScreen;
use Tracy\Debugger;
use Tracy\Helpers;

class Panel implements \Tracy\IBarPanel
{

    use \Nette\SmartObject;

    /**
     * @var \Kdyby\Translation\Translator
     */
    private $translator;

    /**
     * @var array<int|string, array<string|object|null>>
     */
    private $untranslated = [];

    /**
     * @var array<string, array<string>>
     */
    private $resources = [];

    /**
     * @var array<string, array<string>>
     */
    private $ignoredResources = [];

    /**
     * @var array<string>
     */
    private $localeWhitelist = [];

    /**
     * @var array<\Kdyby\Translation\IUserLocaleResolver>
     */
    private $localeResolvers = [];

    /**
     * @var array<array<string, array<string|null>|object|string|null>>
     */
    private $onRequestLocaleSnapshot = [];

    /**
     * @var string
     */
    private $rootDir;

    /**
     * @param string $rootDir
     */
    public function __construct($rootDir)
    {
        $this->rootDir = $rootDir;
    }

    /**
     * Renders HTML code for custom tab.
     *
     * @return string
     */
    public function getTab(): ?string
    {
        return '<span title="Translation"><img width="16px" height="16px" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAAXNSR0IArs4c6QAAAAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB90DExAuL9uIsPUAAAQ2SURBVFjD7Ze9bxxVFMV/583skjgOIXEaKCNFAgmQ+ExS8qEUCEHH30BBScG/QM1fEmgoAAkoAigSKKFASElHESEFbGcdez7uoXhvZteW7ewmQjRMsTs7s/Pufeece+4d+P/4jw8ddvHq1avv2b5mm02Lr9Y7/gpQBCFDD5YhgsAoRLhHMtHnRYNAAb3yt92//9y3v3x+MFY6LIGIuGYbKedngzAGsPK5Kb8BGRAu9xiuL+zQ6NphsQ5NYB7YhA1jQCObyOHG/3sMlpM0hmGNcmXh7w9PYL5wfsphbCMPaOR7BipEJahV0FHZvZ2D+1i2qY+gYERCZTGZzLdFkLNIht/v72AHGM5Pa85MqowUc4p81PaPo2CgIa9RxOZ8jowDZm0WXo2oBdttN8ihIABC5dPLI7BIQQA5prEGIeYdbnVtSSpD3WCaCCqpMB8jXSshEBFEROZ9TCTvepC/be63HWA2ppNRoJttP1flvkp5BArmNBQl5zSIMJtNRxJEwHqVOF1XGLHVdSQp+0CpAi1UytJVYHteBQ7cm7AgIKnAH7CWhBDrVRqfud91UERLFNPyihQMwaOUIAI5I9D0PU2Xz9frCjs4WVVUMhJstR2SwaUWgvx7VQqyG2b4IoIoO9xsWipllJ6sK1JB7Exd4whmbUcYlCVcRKrVq0DSqCIVRlPZ4SCqO7MHC0Ey1Epiu+k5XafMvbRaFXCgdBxBOIgw202/TyNBtmuHibJRYba6LjuAsgjjCA3UxwWPCEwanRCJ7bYd/eHspFootdwhdvqeJsyegyaCSVK271US2E9BNp8otbTd9STlB89NJvRFmJREJ4K7e0El2Op6NiY1x7GQlpoawihgq+lIZF2s1xWdu9GAcO6Yp6rcE4OcwGDGR7nhUglYRjL32pbOQdMH61VCzgiFc8eUs+2cTIkuoI2MmH20D9TLzEzuTafgwtp0dNXO5FlhsOvSh23zzBPTBfDm4ny0BBBWAInWpSmVmSDzqrEko0TpBwct9W+xGgKL41hWoMeJB/LQsa/JlOYkVDrmAHlpwnF0M6of2gdIC7vUvDUNk1HMbwVRbmo+jA3tOlaYByTYbRJdiG1E3UFFg2IvLxqm67qR1yg71kLflDXvwhHhsxtbSycw2xWffXiPP+/NmFTiGyX21t9m99RbWDCZ1Fy5dAlJeXyz6aOfz48Mzli6aPTp8rMXz/585UVeun7z4WW418Kll0/w9Pk1/t5JTHdh2otpElMl1PX8eP06fdus9ObhQ2g4VBrVxpvvdl/Ovmgacev2Dnf+2GXnxi6zGw/KMJrNJUXPS6fXmCaNw+d8GNV8KHXgp87xyg83tVQCALPvLlvCVUK/3tnh9t3mkw8+/u3Tx3kNu/H6C7z6063lnLBU4dd98NHzF9Z47eKpE4/7Hngw+LHHzveX3zmAyBv/xsvpP+li/lm3bxkuAAAAAElFTkSuQmCC" />'
            . $this->translator->getLocale() . ($this->untranslated ? ' <b>(' . count(array_unique($this->untranslated, SORT_REGULAR)) . ' errors)</b>' : '')
            . '</span>';
    }

    /**
     * Renders HTML code for custom panel.
     *
     * @return string
     */
    public function getPanel(): ?string
    {
        $h = 'htmlSpecialChars';

        $panel = [];
        if (!empty($this->untranslated)) {
            $panel[] = $this->renderUntranslated();
        }

        if (!empty($this->onRequestLocaleSnapshot)) {
            if (!empty($panel)) {
                $panel[] = '<br><br>';
            }

            $panel[] = '<h2>Locale resolution</h2>';
            $panel[] = '<p>Order of locale resolvers and final locale for each request</p>';

            foreach ($this->onRequestLocaleSnapshot as $i => $snapshot) {
                $s = $i > 0 ? '<br>' : '';

                /** @var \Nette\Application\Request $sreq  */
                $sreq = (object) $snapshot['request'];
                /** @var array<string> $params  */
                $params = $sreq->getParameters();
                /** @var string $loc  */
                $loc = is_string($snapshot['locale']) ? $snapshot['locale'] : '';
                $s .= '<tr><th width="10px">&nbsp;</th>' .
                    '<th>' . $h($sreq->getPresenterName() . (isset($params['action']) ? ':' . $params['action'] : '')) . '</th>' .
                    '<th>' . $h($loc) . '</th></tr>';

                $l = 1;
                /** @var array<string,\Kdyby\Translation\IUserLocaleResolver> $sres  */
                $sres = $snapshot['resolvers'];
                foreach ($sres as $name => $resolvedLocale) {
                    $s .= '<tr><td>' . ($l++) . '.</td><td>' . $h($name) . '</td><td>' . $h($resolvedLocale::class) . '</td></tr>';
                }

                $panel[] = '<table style="width:100%">' . $s . '</table>';
            }
        }

        if (!empty($this->resources)) {
            if (!empty($panel)) {
                $panel[] = '<br><br>';
            }
            $panel[] = '<h2>Loaded resources</h2>';
            $panel[] = $this->renderResources($this->resources);
        }

        if (!empty($this->ignoredResources)) {
            if (!empty($panel)) {
                $panel[] = '<br><br>';
            }

            $panel[] = '<h2>Ignored resources</h2>';
            $panel[] = '<p>Whitelist config: ' . implode(', ', array_map($h, $this->localeWhitelist)) . '</p>';
            $panel[] = $this->renderResources($this->ignoredResources);
        }

        return empty($panel) ? '' :
            '<h1>Missing translations: ' . count(array_unique($this->untranslated, SORT_REGULAR)) .
            ', Resources: ' . count(Arrays::flatten($this->resources)) . '</h1>' .
            '<div class="nette-inner tracy-inner kdyby-TranslationPanel" style="min-width:500px">' . implode($panel) . '</div>' .
            '<style>
				#nette-debug .kdyby-TranslationPanel h2,
				#tracy-debug .kdyby-TranslationPanel h2 {font-size: 23px;}
			</style>';
    }

    /**
     * 
     * @return string
     */
    private function renderUntranslated(): string
    {
        $s = '';
        $h = 'htmlSpecialChars';

        foreach ($unique = array_unique($this->untranslated, SORT_REGULAR) as $untranslated) {
            $message = $untranslated['message'];

            $s .= '<tr><td>';

            if ($message instanceof \Exception || $message instanceof \Throwable) {
                $s .= '<span style="color:red">' . $h($message->getMessage()) . '</span>';
            } elseif ($message instanceof Html) {
                $s .= '<span style="color:red">Nette\Utils\Html(' . $h((string) $message) . ')</span>';
            } else {
                $s .= $h(is_string($message) ? $message : '');
            }

            $s .= '</td><td>' . $h(is_string($untranslated['domain']) ? $untranslated['domain'] : '') . '</td></tr>';
        }

        return '<table style="width:100%"><tr><th>Untranslated message</th><th>Translation domain</th></tr>' . $s . '</table>';
    }

    /**
     * 
     * @param array<string,array<string,string>> $resourcesMap
     * @return string
     */
    private function renderResources(array $resourcesMap): string
    {
        $s = '';
        $h = 'htmlSpecialChars';

        ksort($resourcesMap);
        foreach ($resourcesMap as $locale => $resources) {
            foreach ($resources as $resourcePath => $domain) {
                $s .= '<tr>';
                $s .= '<td>' . $h($locale) . '</td>';
                $s .= '<td>' . $h($domain) . '</td>';

                $relativePath = str_replace(rtrim($this->rootDir, '/') . '/', '', $resourcePath);
                if (Strings::startsWith($relativePath, 'vendor/')) {
                    $parts = explode('/', $relativePath, 4);
                    $left = array_pop($parts);
                    $relativePath = $h(implode('/', $parts) . '/.../') . '<b>' . $h(basename($left)) . '</b>';
                } else {
                    $relativePath = $h(dirname($relativePath)) . '/<b>' . $h(basename($relativePath)) . '</b>';
                }

                $s .= '<td>' . self::editorLink($resourcePath, 1, $relativePath) . '</td>';
                $s .= '</tr>';
            }
        }

        return '<table style="width:100%"><tr><th>Locale</th><th>Domain</th><th>Resource filename</th></tr>' . $s . '</table>';
    }

    /**
     * @param string $id
     * @param string|NULL $domain
     */
    public function markUntranslated(string $id, ?string $domain): void
    {
        $this->untranslated[] = [
            'message' => $id,
            'domain' => $domain,
        ];
    }

    /**
     * @param \Exception|\Throwable $e
     * @param string|NULL $domain
     */
    public function choiceError(\Exception|\Throwable $e, ?string $domain): void
    {
        $this->untranslated[] = [
            'message' => $e,
            'domain' => $domain,
        ];
    }

    /**
     * @param string|NULL $format
     * @param string|array<string|object> $resource
     * @param string|NULL $locale
     * @param string|NULL $domain
     */
    public function addResource(?string $format, string|array $resource, ?string $locale, ?string $domain): void
    {
        if (is_array($resource)) {
            $resource = 'array ' . md5(serialize($resource));
        }

        $this->resources[$locale][$resource] = $domain ?? '';
    }

    /**
     * 
     * @param array<string> $whitelist
     * @return void
     */
    public function setLocaleWhitelist(array $whitelist): void
    {
        $this->localeWhitelist = $whitelist;
    }

    /**
     * @param string|NULL $format
     * @param string|array<string|object> $resource
     * @param string|NULL $locale
     * @param string|NULL $domain
     */
    public function addIgnoredResource(?string $format, string|array $resource, ?string $locale, ?string $domain): void
    {
        if (is_array($resource)) {
            $resource = 'array ' . md5(serialize($resource));
        }

        $this->ignoredResources[$locale][$resource] = $domain ?? '';
    }

    /**
     * 
     * @param array<\Kdyby\Translation\IUserLocaleResolver> $resolvers
     * @return void
     */
    public function setLocaleResolvers(array $resolvers): void
    {
        $this->localeResolvers = [];
        foreach ($resolvers as $resolver) {
            $this->localeResolvers[ClassType::from($resolver)->getShortName()] = $resolver;
        }
    }

    /**
     * 
     * @param Application $app
     * @param Request $request
     * @return void
     */
    public function onRequest(Application $app, Request $request): void
    {
        if ($this->translator === null) {
            return;
        }

        $snapshot = ['request' => $request, 'locale' => $this->translator->getLocale(), 'resolvers' => []];
        foreach ($this->localeResolvers as $name => $resolver) {
            $snapshot['resolvers'][$name] = $resolver->resolve($this->translator);
        }

        $this->onRequestLocaleSnapshot[] = $snapshot;
    }

    /**
     * 
     * @param Translator $translator
     * @return self
     */
    public function register(Translator $translator): self
    {
        $this->translator = $translator;
        $translator->injectPanel($this);

        Debugger::getBar()->addPanel($this, 'kdyby.translation');

        return $this;
    }

    public static function registerBluescreen(): void
    {
        Debugger::getBlueScreen()->addPanel([get_called_class(), 'renderException']);
    }

    /**
     * 
     * @param ?\Throwable $e
     * @return ?array<string,string>
     */
    public static function renderException(?\Throwable $e = NULL): ?array
    {
        if ($e === null || !$e instanceof \Kdyby\Translation\InvalidResourceException) {
            return NULL;
        }
        /** @var ?\Throwable $test */
        $test = $e->getPrevious();
        if ($test === NULL) {
            return NULL;
        }
        /** @var \Throwable $previous */
        $previous = $e->getPrevious();
        /** @var \Symfony\Component\Yaml\Exception\ParseException $previous1 */
        $previous1 = $previous->getPrevious();
        if (!$previous1 instanceof \Symfony\Component\Yaml\Exception\ParseException) {
            return NULL;
        }

        $method = YamlFileLoader::class . '::load';
        $call = Helpers::findTrace($previous->getTrace(), $method);
        if ($call !== NULL) {
            return [
                'tab' => 'YAML dictionary',
                'panel' => '<p><b>File:</b> ' . self::editorLink($call['args'][0], $previous1->getParsedLine()) . '</p>'
                . ($previous1->getParsedLine() ? BlueScreen::highlightFile($call['args'][0], $previous1->getParsedLine()) : '')
                . '<p>' . $previous1->getMessage() . ' </p>',
            ];
        }
        return NULL;
    }

    /**
     * Returns link to editor.
     * @param string $file
     * @param int $line
     * @param string|null $text
     * @return \Nette\Utils\Html|string
     */
    private static function editorLink(string $file, int $line, ?string $text = NULL): \Nette\Utils\Html|string
    {
        if (Debugger::$editor && is_file($file) && $text !== NULL) {
            return Html::el('a')
                    ->href(strtr(Debugger::$editor, ['%file' => rawurlencode($file), '%line' => $line]))
                    ->setAttribute('title', sprintf('%s:%s', $file, $line))
                    ->setHtml($text);
        } else {
            return Helpers::editorLink($file, $line);
        }
    }

}
