<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\MessageCatalogueProvider;

use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Translator;

/**
 * MessageCatalogueProvider loads catalogue from resources.
 *
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 */
class ResourceMessageCatalogueProvider implements MessageCatalogueProviderInterface
{
    /**
     * @var array
     */
    private $resources = array();

    /**
     * @var LoaderInterface[] An array of LoaderInterface objects
     */
    private $loaders = array();

    /**
     * @var array
     */
    private $isFresh = array();

    /**
     * @param LoaderInterface[] $loaders   An array of loaders
     * @param array             $resources An array of resources
     */
    public function __construct(array $loaders = array(), $resources = array())
    {
        foreach ($loaders as $format => $loader) {
            $this->addLoader($format, $loader);
        }

        foreach ($resources as $resource) {
            $this->addResource($resource[0], $resource[1], $resource[2], isset($resource[3]) ? $resource[3] : null);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh($locale)
    {
        return isset($this->isFresh[$locale]) ? $this->isFresh[$locale] : true;
    }

    /**
     * {@inheritdoc}
     */
    public function getCatalogue($locale)
    {
        $catalogue = new MessageCatalogue($locale);
        foreach ($this->getResources($locale) as $resource) {
            $catalogue->addCatalogue($this->loadResource($resource[0], $resource[1], $locale, $resource[2]));
        }

        $this->isFresh[$locale] = true;

        return $catalogue;
    }

    /**
     * Adds a Resource.
     *
     * @param string $format   The name of the loader (@see addLoader())
     * @param mixed  $resource The resource name
     * @param string $locale   The locale
     * @param string $domain   The domain
     */
    public function addResource($format, $resource, $locale, $domain = null)
    {
        Translator::assertLocale($locale);

        if (null === $domain) {
            $domain = 'messages';
        }

        $this->resources[$locale][] = array($format, $resource, $domain);
        if (isset($this->isFresh[$locale])) {
            $this->isFresh[$locale] = false;
        }
    }

    /**
     * Adds a Loader.
     *
     * @param string          $format The name of the loader (@see addResource())
     * @param LoaderInterface $loader A LoaderInterface instance
     */
    public function addLoader($format, LoaderInterface $loader)
    {
        $this->loaders[$format] = $loader;
    }

    /**
     * Returns the registered loaders.
     *
     * @return LoaderInterface[] An array of LoaderInterface instances
     */
    public function getLoaders()
    {
        return $this->loaders;
    }

    /**
     * @return array
     */
    public function getResources($locale)
    {
        return isset($this->resources[$locale]) ? $this->resources[$locale] : array();
    }

    private function loadResource($format, $resource, $locale, $domain)
    {
        $loaders = $this->getLoaders();
        if (!isset($loaders[$format])) {
            throw new \RuntimeException(sprintf('The "%s" translation loader is not registered.', $format));
        }

        return $this->loaders[$format]->load($resource, $locale, $domain);
    }
}
