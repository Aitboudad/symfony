<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\MessageCatalogueProvider\Tests;

use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\MessageCatalogueProvider\ResourceMessageCatalogueProvider;
use Symfony\Component\Translation\MessageCatalogueProvider\FallbackMessageCatalogueProvider;

class FallbackMessageCatalogueProviderTest extends \PHPUnit_Framework_TestCase
{
    public function testSetFallbackLocales()
    {
        $loaders = array('array' => new ArrayLoader());
        $resources = array(
            array('array', array('foo' => 'foofoo'), 'en'),
            array('array', array('bar' => 'foobar'), 'fr'),
        );

        // load catalogue
        $translatorBag = $this->getMessageCatalogueProvider(array(), $loaders, $resources);
        $translatorBag->setFallbackLocales(array('fr'));

        $catalogue = $translatorBag->getCatalogue('en');
        $this->assertEquals('foobar', $catalogue->get('bar'));
    }

    /**
     * @dataProvider      getInvalidLocalesTests
     * @expectedException \InvalidArgumentException
     */
    public function testSetFallbackInvalidLocales($locale)
    {
        $this->getMessageCatalogueProvider(array($locale));
    }

    /**
     * @dataProvider getValidLocalesTests
     */
    public function testSetFallbackValidLocales($locale)
    {
        $this->getMessageCatalogueProvider(array($locale));
        // no assertion. this method just asserts that no exception is thrown
    }

    public function testLoadCatalogueWithFallbackLocale()
    {
        $loaders = array('array' => new ArrayLoader());
        $resources = array(
            array('array', array('bar' => 'foobar'), 'en'),
        );
        $translatorBag = $this->getMessageCatalogueProvider(array('en'), $loaders, $resources);

        // load catalogue
        $catalogue = $translatorBag->getCatalogue('fr_FR');

        $this->assertEquals('foobar', $catalogue->get('bar'));
    }

    private function getMessageCatalogueProvider($locales = array(), $loaders = array(), $resources = array())
    {
        $translatorBag = new ResourceMessageCatalogueProvider($loaders, $resources);

        return new FallbackMessageCatalogueProvider($translatorBag, $locales);
    }

    public function getInvalidLocalesTests()
    {
        return array(
            array('fr FR'),
            array('français'),
            array('fr+en'),
            array('utf#8'),
            array('fr&en'),
            array('fr~FR'),
            array(' fr'),
            array('fr '),
            array('fr*'),
            array('fr/FR'),
            array('fr\\FR'),
        );
    }

    public function getValidLocalesTests()
    {
        return array(
            array(''),
            array(null),
            array('fr'),
            array('francais'),
            array('FR'),
            array('frFR'),
            array('fr-FR'),
            array('fr_FR'),
            array('fr.FR'),
            array('fr-FR.UTF8'),
            array('sr@latin'),
        );
    }
}
