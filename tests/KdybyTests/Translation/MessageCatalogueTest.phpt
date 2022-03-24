<?php declare(strict_types = 1);

/**
 * Test: Kdyby\Translation\MessageCatalogue.
 *
 * @testCase KdybyTests\Translation\MessageCatalogueTest
 */

namespace KdybyTests\Translation;

use Kdyby\Translation\MessageCatalogue;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class MessageCatalogueTest extends TestCase
{

    /** @var MessageCatalogue */
    protected $catalogue;

    protected function setUp()
    {
        $this->catalogue = new MessageCatalogue('cs_CZ', [
            'front' => [
                'homepage.hello' => 'Ahoj světe!',
            ],
        ]);
    }

    public function testGet()
    {
        Assert::same('Ahoj světe!', $this->catalogue->get('homepage.hello', 'front'));
    }

    public function testGetUntranslated()
    {
        Assert::same("\x01", $this->catalogue->get('missing', 'front'));
        Assert::same("\x01", $this->catalogue->get('foo', 'missing'));
    }

}

(new MessageCatalogueTest())->run();
