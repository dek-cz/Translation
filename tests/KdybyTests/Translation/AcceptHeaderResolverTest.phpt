<?php declare(strict_types = 1);

/**
 * Test: Kdyby\Translation\AcceptHeaderResolver.
 *
 * @testCase
 */

namespace KdybyTests\Translation;

use Kdyby\Translation\LocaleResolver\AcceptHeaderResolver;
use Kdyby\Translation\Translator;
use Mockery;
use Nette\Http\IRequest;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

class AcceptHeaderResolverTest extends TestCase
{

    public function testResolve()
    {
        Assert::null($this->resolve(null, ['xx']));
        Assert::null($this->resolve('en', []));
        Assert::null($this->resolve('garbage', ['en']));
        Assert::same('en', $this->resolve('en, cs', ['en', 'cs']));
        Assert::same('en', $this->resolve('en, cs', ['cs', 'en']));
        Assert::same('en-gb', $this->resolve('da, en-gb;q=0.8, en;q=0.7', ['en', 'en-gb']));
        Assert::same('en', $this->resolve('da, en-gb;q=0.8, en;q=0.7', ['en']));
        Assert::same('en', $this->resolve('da, en_gb', ['en']));
        Assert::same('en-gb', $this->resolve('da, en_gb', ['en', 'en-gb']));
    }

    protected function resolve($header, array $locales)
    {
        $httpRequest = Mockery::mock(IRequest::class);
        $httpRequest->shouldReceive('getHeader')->with(AcceptHeaderResolver::ACCEPT_LANGUAGE_HEADER)->andReturn($header);

        $acceptHeaderResolver = new AcceptHeaderResolver($httpRequest);

        $translator = Mockery::mock(Translator::class);
        $translator->shouldReceive('getAvailableLocales')->andReturn($locales);

        return $acceptHeaderResolver->resolve($translator);
    }

    protected function tearDown()
    {
        Mockery::close();
    }

}

(new AcceptHeaderResolverTest())->run();
