<?php declare(strict_types = 1);

/**
 * Test: Kdyby\Translation\FallbackResolver.
 *
 * @testCase KdybyTests\Translation\FallbackResolverTest
 */

namespace KdybyTests\Translation;

use Kdyby\Translation\FallbackResolver;
use Kdyby\Translation\Translator;
use Nette\Localization\ITranslator;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

class FallbackResolverTest extends TestCase
{

    public function testCompute()
    {
        $container = $this->createContainer();

        /** @var Translator $translator */
        $translator = $container->getByType(ITranslator::class);
        $translator->setFallbackLocales(['cs_CZ', 'cs']);

        /** @var FallbackResolver $fallbackResolver */
        $fallbackResolver = $container->getByType(FallbackResolver::class);

        Assert::same(['cs_CZ'], $fallbackResolver->compute($translator, 'cs'));
        Assert::same(['sk_SK', 'cs_CZ', 'cs'], $fallbackResolver->compute($translator, 'sk'));
        Assert::same(['sk', 'cs_CZ', 'cs'], $fallbackResolver->compute($translator, 'sk_SK'));
        Assert::same(['en_US', 'cs_CZ', 'cs'], $fallbackResolver->compute($translator, 'en'));
        Assert::same(['en', 'cs_CZ', 'cs'], $fallbackResolver->compute($translator, 'en_US'));
    }

}

(new FallbackResolverTest())->run();
