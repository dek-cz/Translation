<?php declare(strict_types = 1);

/**
 * Test: Kdyby\Translation\LocaleParamResolver.
 *
 * @testCase
 */

namespace KdybyTests\Translation;

use Kdyby\Translation\LocaleResolver\SessionResolver;
use Kdyby\Translation\Translator;
use Nette\Application\Application;
use Nette\Application\Request as AppRequest;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

class LocaleSessionResolverTest extends TestCase
{

    public function testInvalidateLocaleOnRequest()
    {
        $container = $this->createContainer();

        /** @var Translator $translator */
        $translator = $container->getByType(Translator::class);

        /** @var Application $app */
        $app = $container->getByType(Application::class);

        /** @var SessionResolver $sessionResolver */
        $sessionResolver = $container->getByType(SessionResolver::class);

        // this should fallback to default locale
        Assert::same('en', $translator->getLocale());

        // force cs locale
        $sessionResolver->setLocale('cs');

        // locale from request parameter should be ignored
        $app->onRequest($app, new AppRequest('Test', 'GET', ['action' => 'default', 'locale' => 'en']));
        Assert::same('cs', $translator->getLocale());

        // force en locale
        $sessionResolver->setLocale('en');

        // locale from request parameter should be ignored
        $app->onRequest($app, new AppRequest('Test', 'GET', ['action' => 'default', 'locale' => 'cs']));
        Assert::same('en', $translator->getLocale());
    }

}

(new LocaleSessionResolverTest())->run();
