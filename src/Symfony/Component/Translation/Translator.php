<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

use Symfony\Component\Translation\MessageCatalogueProvider\MessageCatalogueProviderInterface;
use Symfony\Component\Translation\MessageCatalogueProvider\ResourceMessageCatalogueProvider;
use Symfony\Component\Translation\MessageCatalogueProvider\FallbackMessageCatalogueProvider;
use Symfony\Component\Translation\MessageCatalogueProvider\CacheMessageCatalogueProvider;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Symfony\Component\Config\ConfigCacheFactoryInterface;

/**
 * Translator.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Translator implements TranslatorInterface, TranslatorBagInterface
{
    /**
     * @var MessageCatalogueInterface[]
     */
    protected $catalogues = array();

    /**
     * @var string
     */
    protected $locale;

    /**
     * @var MessageSelector
     */
    private $selector;

    /**
     * @var string
     */
    private $cacheDir;

    /**
     * @var MessageCatalogueProviderInterface
     */
    private $messageCatalogueProvider;

    /**
     * @var ResourceMessageCatalogueProvider
     */
    private $resourceMessageCatalogueProvider;

    /**
     * @var FallbackMessageCatalogueProvider
     */
    private $fallbackMessageCatalogueProvider;

    /**
     * @var CacheMessageCatalogueProvider
     */
    private $cacheMessageCatalogueProvider;

    /**
     * @param string                                                 $locale                   The locale
     * @param MessageCatalogueProviderInterface|MessageSelector|null $messageCatalogueProvider The MessageCatalogueProviderInterface or MessageSelector
     *                                                                                         Passing the MessageSelector or null as a second parameter is deprecated since version 2.8.
     * @param MessageSelector|string|null                            $selector                 The MessageSelector or cache directory
     *                                                                                         Passing the cache directory as a third parameter is deprecated since version 2.8.
     * @param bool                                                   $debug                    Use cache in debug mode ?
     *                                                                                         Deprecated since version 2.8, to be removed in 3.0.
     *
     * @throws \InvalidArgumentException If a locale contains invalid characters
     */
    public function __construct($locale, $messageCatalogueProvider = null, $selector = null, $debug = false)
    {
        $this->setLocale($locale);

        if ($messageCatalogueProvider instanceof MessageCatalogueProviderInterface) {
            $this->messageCatalogueProvider = $messageCatalogueProvider;
            $this->selector = $selector ?: new MessageSelector();

            do {
                if ($messageCatalogueProvider instanceof CacheMessageCatalogueProvider) {
                    $this->cacheMessageCatalogueProvider = $messageCatalogueProvider;
                } elseif ($messageCatalogueProvider instanceof FallbackMessageCatalogueProvider) {
                    $this->fallbackMessageCatalogueProvider = $messageCatalogueProvider;
                } elseif ($messageCatalogueProvider instanceof ResourceMessageCatalogueProvider) {
                    $this->resourceMessageCatalogueProvider = $messageCatalogueProvider;
                }

                $messageCatalogueProvider = method_exists($messageCatalogueProvider, 'getInner') ? $messageCatalogueProvider->getInner() : null;
            } while ($messageCatalogueProvider);
        } else {
            @trigger_error('The '.__CLASS__.' constructor will require a MessageCatalogueProviderInterface for second parameter since 3.0.', E_USER_DEPRECATED);

            // Parameters are shifted of one offset
            $this->selector = $messageCatalogueProvider ?: new MessageSelector();
            $cacheDir = $selector;

            $this->resourceMessageCatalogueProvider = new ResourceMessageCatalogueProvider();
            $this->fallbackMessageCatalogueProvider = new FallbackMessageCatalogueProvider($this->resourceMessageCatalogueProvider);
            $this->messageCatalogueProvider = $this->fallbackMessageCatalogueProvider;
            if ($cacheDir) {
                $this->cacheMessageCatalogueProvider = new CacheMessageCatalogueProvider($this->fallbackMessageCatalogueProvider, $cacheDir, $debug);
                $this->messageCatalogueProvider = $this->cacheMessageCatalogueProvider;
            }
        }

        if (!$this->selector instanceof MessageSelector) {
            throw new \InvalidArgumentException(sprintf('The message selector "%s" must be an instance of MessageSelector.', get_class($this->selector)));
        }
    }

    /**
     * Sets the ConfigCache factory to use.
     *
     * @param ConfigCacheFactoryInterface $configCacheFactory
     *
     * @deprecated since version 2.8, to be removed in 3.0. Rely on CacheMessageCatalogueProvider instead.
     */
    public function setConfigCacheFactory(ConfigCacheFactoryInterface $configCacheFactory)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.8 and will be removed in 3.0. Rely on CacheMessageCatalogueProvider instead.', E_USER_DEPRECATED);

        $this->cacheMessageCatalogueProvider->getConfigCacheFactory($configCacheFactory);
    }

    /**
     * Adds a Loader.
     *
     * @param string          $format The name of the loader (@see addResource())
     * @param LoaderInterface $loader A LoaderInterface instance
     *
     * @deprecated since version 2.8, to be removed in 3.0. Use ResourceMessageCatalogueProvider::addLoader instead.
     */
    public function addLoader($format, LoaderInterface $loader)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.8 and will be removed in 3.0. Use ResourceMessageCatalogueProvider::addLoader instead.', E_USER_DEPRECATED);

        $this->resourceMessageCatalogueProvider->addLoader($format, $loader);
    }

    /**
     * Adds a Resource.
     *
     * @param string $format   The name of the loader (@see addLoader())
     * @param mixed  $resource The resource name
     * @param string $locale   The locale
     * @param string $domain   The domain
     *
     * @throws \InvalidArgumentException If the locale contains invalid characters
     *
     * @deprecated since version 2.8, to be removed in 3.0. Use ResourceMessageCatalogueProvider::addResource instead.
     */
    public function addResource($format, $resource, $locale, $domain = null)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.8 and will be removed in 3.0. Use ResourceMessageCatalogueProvider::addResource instead.', E_USER_DEPRECATED);

        $this->resourceMessageCatalogueProvider->addResource($format, $resource, $locale, $domain);
    }

    /**
     * {@inheritdoc}
     */
    public function setLocale($locale)
    {
        $this->assertValidLocale($locale);
        $this->locale = $locale;
    }

    /**
     * {@inheritdoc}
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Sets the fallback locale(s).
     *
     * @param string|array $locales The fallback locale(s)
     *
     * @throws \InvalidArgumentException If a locale contains invalid characters
     *
     * @deprecated since version 2.3, to be removed in 3.0. Use setFallbackLocales() instead.
     */
    public function setFallbackLocale($locales)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.3 and will be removed in 3.0. Use the setFallbackLocales() method instead.', E_USER_DEPRECATED);

        $this->setFallbackLocales(is_array($locales) ? $locales : array($locales));
    }

    /**
     * Sets the fallback locales.
     *
     * @param array $locales The fallback locales
     *
     * @throws \InvalidArgumentException If a locale contains invalid characters
     *
     * @deprecated since version 2.8, to be removed in 3.0. Use FallbackMessageCatalogueProvider::setFallbackLocales instead.
     */
    public function setFallbackLocales(array $locales)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.8 and will be removed in 3.0. Use FallbackMessageCatalogueProvider::setFallbackLocales instead.', E_USER_DEPRECATED);

        $this->fallbackMessageCatalogueProvider->setFallbackLocales($locales);
    }

    /**
     * Gets the fallback locales.
     *
     * @return array $locales The fallback locales
     *
     * @deprecated since version 2.8, to be removed in 3.0. Use FallbackMessageCatalogueProvider::getFallbackLocales instead.
     */
    public function getFallbackLocales()
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.8 and will be removed in 3.0. Use FallbackMessageCatalogueProvider::getFallbackLocales instead.', E_USER_DEPRECATED);

        return $this->fallbackMessageCatalogueProvider->getFallbackLocales();
    }

    /**
     * {@inheritdoc}
     */
    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        if (null === $domain) {
            $domain = 'messages';
        }

        return strtr($this->getCatalogue($locale)->get((string) $id, $domain), $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function transChoice($id, $number, array $parameters = array(), $domain = null, $locale = null)
    {
        if (null === $domain) {
            $domain = 'messages';
        }

        $id = (string) $id;
        $catalogue = $this->getCatalogue($locale);
        $locale = $catalogue->getLocale();
        while (!$catalogue->defines($id, $domain)) {
            if ($cat = $catalogue->getFallbackCatalogue()) {
                $catalogue = $cat;
                $locale = $catalogue->getLocale();
            } else {
                break;
            }
        }

        return strtr($this->selector->choose($catalogue->get($id, $domain), (int) $number, $locale), $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function getCatalogue($locale = null)
    {
        if (null === $locale) {
            $locale = $this->getLocale();
        } else {
            $this->assertValidLocale($locale);
        }

        if (isset($this->catalogues[$locale]) && $this->messageCatalogueProvider->isFresh($locale)) {
            return $this->catalogues[$locale];
        }

        // check if the Translator class is overrated
        if ('Symfony\Component\Translation\Translator' !== get_class($this)) {
            $reflector = new \ReflectionClass($this);

            $reflectorMethod = $reflector->getMethod('loadCatalogue');
            $isOverwritten = ($reflectorMethod->getDeclaringClass()->getName() !== 'Symfony\Component\Translation\Translator');
            if (true === $isOverwritten) {
                @trigger_error('The Translator::loadCatalogue method is deprecated since version 2.8 and will be removed in 3.0. Rely on MessageCatalogueProviderInterface::getCatalogue() instead.', E_USER_DEPRECATED);
            }

            $reflectorMethod = $reflector->getMethod('initializeCatalogue');
            $isOverwritten = ($reflectorMethod->getDeclaringClass()->getName() !== 'Symfony\Component\Translation\Translator');
            if (true === $isOverwritten) {
                @trigger_error('The Translator::initializeCatalogue method is deprecated since version 2.8 and will be removed in 3.0. Rely on MessageCatalogueProviderInterface::getCatalogue() instead.', E_USER_DEPRECATED);
            }

            $reflectorMethod = $reflector->getMethod('computeFallbackLocales');
            $isOverwritten = ($reflectorMethod->getDeclaringClass()->getName() !== 'Symfony\Component\Translation\Translator');
            if (true === $isOverwritten) {
                @trigger_error('The Translator::computeFallbackLocales method is deprecated since version 2.8 and will be removed in 3.0. Rely on FallbackMessageCatalogueProvider instead.', E_USER_DEPRECATED);
            }

            $reflectorMethod = $reflector->getMethod('getLoaders');
            $isOverwritten = ($reflectorMethod->getDeclaringClass()->getName() !== 'Symfony\Component\Translation\Translator');
            if (true === $isOverwritten) {
                @trigger_error('The Translator::getLoaders method is deprecated since version 2.8 and will be removed in 3.0. Rely on ResourceMessageCatalogueProvider::getLoaders instead.', E_USER_DEPRECATED);
            }

            $this->loadCatalogue($locale);

            return $this->catalogues[$locale];
        }

        return $this->catalogues[$locale] = $this->messageCatalogueProvider->getCatalogue($locale);
    }

    /**
     * Gets the loaders.
     *
     * @return array LoaderInterface[]
     *
     * @deprecated since version 2.8, to be removed in 3.0. Rely on ResourceMessageCatalogueProvider::getLoaders instead.
     */
    protected function getLoaders()
    {
        return $this->resourceMessageCatalogueProvider->getLoaders();
    }

    /**
     * Collects all messages for the given locale.
     *
     * @param string|null $locale Locale of translations, by default is current locale
     *
     * @return array[array] indexed by catalog
     *
     * @deprecated since version 2.8, to be removed in 3.0. Use ResourceMessageCatalogueProviderInterface::getCatalogue() method instead.
     */
    public function getMessages($locale = null)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.8 and will be removed in 3.0. Use ResourceMessageCatalogueProviderInterface::getCatalogue() method instead.', E_USER_DEPRECATED);

        $catalogue = $this->getCatalogue($locale);
        $messages = $catalogue->all();
        while ($catalogue = $catalogue->getFallbackCatalogue()) {
            $messages = array_replace_recursive($catalogue->all(), $messages);
        }

        return $messages;
    }

    /**
     * @param string $locale
     *
     * @deprecated since version 2.8, to be removed in 3.0. Rely on MessageCatalogueProviderInterface::getCatalogue instead.
     */
    protected function loadCatalogue($locale)
    {
        if (null === $this->cacheMessageCatalogueProvider) {
            $this->initializeCatalogue($locale);
        } else {
            $this->initializeCacheCatalogue($locale);
        }
    }

    /**
     * @param string $locale
     *
     * @deprecated since version 2.8, to be removed in 3.0. Rely on MessageCatalogueProviderInterface::getCatalogue instead.
     */
    protected function initializeCatalogue($locale)
    {
        $this->assertValidLocale($locale);

        try {
            $this->catalogues[$locale] = $this->resourceMessageCatalogueProvider->getCatalogue($locale);
        } catch (NotFoundResourceException $e) {
            if (!$this->computeFallbackLocales($locale)) {
                throw $e;
            }
        }
        // load Fallback Catalogues
        $current = $this->catalogues[$locale];
        foreach ($this->computeFallbackLocales($locale) as $fallback) {
            if (!isset($this->catalogues[$fallback])) {
                $this->catalogues[$fallback] = $this->resourceMessageCatalogueProvider->getCatalogue($fallback);
            }

            $fallbackCatalogue = new MessageCatalogue($fallback, $this->catalogues[$fallback]->all());
            $current->addFallbackCatalogue($fallbackCatalogue);
            $current = $fallbackCatalogue;
        }
    }

    /**
     * This method is public because it needs to be callable from a closure in PHP 5.3. It should be removed in 3.0.
     *
     * @internal
     */
    public function initializeAndGetCatalogue($locale)
    {
        $this->initializeCatalogue($locale);

        return $this->catalogues[$locale];
    }

    /**
     * @param string $locale
     */
    private function initializeCacheCatalogue($locale)
    {
        if (isset($this->catalogues[$locale])) {
            /* Catalogue already initialized. */
            return;
        }

        $this->assertValidLocale($locale);
        $self = $this; // required for PHP 5.3 where "$this" cannot be use()d in anonymous functions. Change in Symfony 3.0.
        $cache = $this->cacheMessageCatalogueProvider->cache($locale, function () use ($self, $locale) {
            return $self->initializeAndGetCatalogue($locale);
        });

        if (isset($this->catalogues[$locale])) {
            /* Catalogue has been initialized as it was written out to cache. */
            return;
        }

        /* Read catalogue from cache. */
        $this->catalogues[$locale] = $cache;
    }

    /**
     * @deprecated since version 2.8, to be removed in 3.0. Rely on FallbackMessageCatalogueProvider instead.
     */
    protected function computeFallbackLocales($locale)
    {
        return $this->fallbackMessageCatalogueProvider->computeFallbackLocales($locale);
    }

    /**
     * Asserts that the locale is valid, throws an Exception if not.
     *
     * @param string $locale Locale to tests
     *
     * @throws \InvalidArgumentException If the locale contains invalid characters
     */
    protected function assertValidLocale($locale)
    {
        self::assertLocale($locale);
    }

    /**
     * Asserts that the locale is valid, throws an Exception if not.
     *
     * @param string $locale Locale to tests
     *
     * @throws \InvalidArgumentException If the locale contains invalid characters
     */
    public static function assertLocale($locale)
    {
        if (1 !== preg_match('/^[a-z0-9@_\\.\\-]*$/i', $locale)) {
            throw new \InvalidArgumentException(sprintf('Invalid "%s" locale.', $locale));
        }
    }
}
