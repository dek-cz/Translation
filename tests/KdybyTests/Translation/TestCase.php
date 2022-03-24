<?php declare(strict_types = 1);

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace KdybyTests\Translation;

use Contributte\Console\DI\ConsoleExtension;
use Contributte\Monolog\DI\MonologExtension;
use Kdyby\Translation\DI\TranslationExtension;
use Kdyby\Translation\Translator;
use Nette\Configurator;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\Localization\ITranslator;

abstract class TestCase extends \Tester\TestCase
{

    /**
     * @param string|NULL $configName
     * @return Container
     */
    protected function createContainer($configName = null)
    {
        $config = new Configurator();
        $config->setTempDirectory(TEMP_DIR);
        $config->addParameters(['appDir' => __DIR__]);
        TranslationExtension::register($config);
        $config->onCompile[] = function ($config, Compiler $compiler): void {
            $compiler->addExtension('monolog', new MonologExtension());
        };
        $config->onCompile[] = function ($config, Compiler $compiler): void {
            $compiler->addExtension('console', new ConsoleExtension(true));
        };
        $config->addConfig(__DIR__ . '/../nette-reset.neon');

        if ($configName) {
            $config->addConfig(__DIR__ . '/config/' . $configName . '.neon');
        }

//        try{
//            $config->createContainer();
//        } catch (\Exception $ex) {
//            var_dump($ex->getMessage());
//            exit;
//        }
        return $config->createContainer();
    }

    /**
     * @param string|NULL $configName
     * @return Translator
     */
    protected function createTranslator($configName = null)
    {
        $container = $this->createContainer($configName);
        /** @var Translator $translator */
        $translator = $container->getByType(ITranslator::class);
        // type hacking
        return $translator;
    }

}
