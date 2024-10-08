<?php declare(strict_types = 1);

/**
 * Test: Kdyby\Translation\CatalogueCompiler.
 *
 * @testCase
 */

namespace KdybyTests\Translation;

use Kdyby\Translation\CatalogueCompiler;
use Kdyby\Translation\Translator;
use Nette\Localization\ITranslator;
use Symfony\Component\Translation\MessageCatalogue;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

class CatalogueCompilerTest extends TestCase
{

    public function testFallbacks()
    {
        $container = $this->createContainer();

        /** @var Translator $translator */
        $translator = $container->getByType(ITranslator::class);
        $translator->setFallbackLocales(['cs_CZ', 'cs']);

        Assert::same('Ahoj světe', $translator->translate('homepage.hello', null, [], 'front', 'fr'));

        $translator->setFallbackLocales([]);
        Assert::same('homepage.hello', $translator->translate('homepage.hello', null, [], 'front', 'fr'));

        $translator->setFallbackLocales(['en_US', 'en']);
        Assert::same('Hello world', $translator->translate('homepage.hello', null, [], 'front', 'fr'));
    }

    public function testLocaleEscaping()
    {
        $container = $this->createContainer();

        /** @var Translator $translator */
        $translator = $container->getByType(ITranslator::class);
        $translator->setFallbackLocales(['fr_FR', 'fr.UTF8', 'cs_CZ', 'cs']);

        /** @var CatalogueCompiler $compiler */
        $compiler = $container->getByType(CatalogueCompiler::class);

        $catalogues = [];
        $compiler->compile($translator, $catalogues, 'fr-FR.UTF8');

        $tempFiles = array_filter(get_included_files(), function ($path) {
            return stripos(realpath($path), realpath(TEMP_DIR)) !== false && stripos($path, 'fr-FR.UTF8');
        });

        Assert::count(1, $tempFiles);

        $compiledCatalogue = call_user_func(function ($__file) use (&$__definedVariables) {
            $__definedVariables = get_defined_vars() + ['__compiled' => null];
            $__compiled = include $__file;
            $__definedVariables = array_diff_key(get_defined_vars(), $__definedVariables);

            return $__compiled;
        }, reset($tempFiles));

        Assert::type(MessageCatalogue::class, $compiledCatalogue);
        Assert::same([
            'catalogue',
            'catalogueFr_FR',
            'catalogueFr_UTF8',
            'catalogueCs_CZ',
            'catalogueCs',
            ], array_keys($__definedVariables));
    }

}

(new CatalogueCompilerTest())->run();
