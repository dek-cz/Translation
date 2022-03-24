<?php declare(strict_types = 1);

/**
 * Test: Kdyby\Translation\Extension.
 *
 * @testCase KdybyTests\Translation\ExtensionTest
 */

namespace KdybyTests\Translation;

use Kdyby\Translation\TranslationLoader;
use Kdyby\Translation\Translator as KdybyTranslator;
use Monolog\Logger;
use Nette\Localization\ITranslator;
use Symfony\Component\Translation\Translator as SymfonyTranslator;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

class ExtensionTest extends TestCase
{

    public function testFunctionality()
    {
        $translator = $this->createTranslator();

        Assert::true($translator instanceof ITranslator);
        Assert::true($translator instanceof KdybyTranslator);
        Assert::true($translator instanceof SymfonyTranslator);

        Assert::same('Ahoj světe', $translator->translate('homepage.hello', null, [], 'front', 'cs'));
        Assert::same('Hello world', $translator->translate('homepage.hello', null, [], 'front', 'en'));

        Assert::same('front.not.found', $translator->translate('front.not.found'));
    }

    public function testResolvers()
    {
        $sl = $this->createContainer('resolvers.default-only');

        /** @var KdybyTranslator $translator */
        $translator = $sl->getByType(KdybyTranslator::class);
        var_dump($translator->getDefaultLocale());
        Assert::same('cs', $translator->getLocale());
        exit;
    }

    public function testLoaders()
    {
        $sl = $this->createContainer('loaders.custom');

        /** @var TranslationLoader $loader */
        $loader = $sl->getService('translation.loader');

        $loaders = $loader->getLoaders();
        Assert::count(2, $loaders);
        Assert::true(array_key_exists('php', $loaders));
        Assert::true(array_key_exists('neon', $loaders));
        Assert::false(array_key_exists('po', $loaders));
        Assert::false(array_key_exists('dat', $loaders));
        Assert::false(array_key_exists('csv', $loaders));
    }

    public function testLogging()
    {
        $sl = $this->createContainer('logging');
        $logger = $sl->getService('monolog.logger.translation');
        $loggingHandler = $sl->getService('monolog.logger.translation.handler.0');
        $translator = $sl->getByType(KdybyTranslator::class); //translation.default
        $translator->injectPsrLogger($logger);
        Assert::same('front.not.found', $translator->translate('front.not.found'));

        [$record] = $loggingHandler->getRecords();
        Assert::same('Missing translation', $record['message']);
        Assert::same(Logger::NOTICE, $record['level']);
        Assert::same('translation', $record['channel']);
        Assert::same([
            'message' => 'front.not.found',
            'domain' => 'front',
            'locale' => 'en',
            ], $record['context']);
    }

}

(new ExtensionTest())->run();
