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

use Symfony\Component\Config\ConfigCacheInterface;
use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheFactory;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * Manages cache catalogues.
 *
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 */
class CacheMessageCatalogueProvider implements MessageCatalogueProviderInterface
{
    /**
     * @var MessageCatalogueProviderInterface
     */
    private $messageCatalogueProvider;

    /**
     * @var string
     */
    private $cacheDir;

    /**
     * @var ConfigCacheFactoryInterface
     */
    private $configCacheFactory;

    /**
     * @param MessageCatalogueProviderInterface $messageCatalogueProvider The catalogue bag to use for loading the catalogue.
     * @param string                            $cacheDir                 The directory to use for the cache.
     * @param bool                              $debug                    Use cache in debug mode.
     * @param ConfigCacheFactoryInterface|null  $configCacheFactory       The ConfigCache factory to use.
     */
    public function __construct(MessageCatalogueProviderInterface $messageCatalogueProvider, $cacheDir, $debug = false, ConfigCacheFactoryInterface $configCacheFactory = null)
    {
        $this->messageCatalogueProvider = $messageCatalogueProvider;
        $this->cacheDir = $cacheDir;
        $this->configCacheFactory = $configCacheFactory ?: new ConfigCacheFactory($debug);
    }

    /**
     * {@inheritdoc}
     */
    public function getCatalogue($locale)
    {
        $messageCatalogueProvider = $this->messageCatalogueProvider;

        return $this->cache($locale, function () use ($messageCatalogueProvider, $locale) {
            return $messageCatalogueProvider->getCatalogue($locale);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh($locale)
    {
        return $this->messageCatalogueProvider->isFresh($locale);
    }

    /**
     * This method is added because it needs in the Translator for BC. It should be removed in 3.0.
     *
     * @internal
     */
    public function getInner()
    {
        return $this->messageCatalogueProvider;
    }

    /**
     * This method is added because it needs in the Translator for BC. It should be removed in 3.0.
     *
     * @internal
     */
    public function cache($locale, $callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException(sprintf('Invalid type for callback argument. Expected callable, but got "%s".', gettype($callback)));
        }

        $self = $this; // required for PHP 5.3 where "$this" cannot be use()d in anonymous functions. Change in Symfony 3.0.
        $cache = $this->configCacheFactory->cache($this->getCatalogueCachePath($locale),
            function (ConfigCacheInterface $cache) use ($self, $callback) {
                $self->dumpCatalogue($callback(), $cache);
            }
        );

        /* Read catalogue from cache. */
        return include $cache->getPath();
    }

    /**
     * Sets the ConfigCache factory to use.
     *
     * This method is added because it needs in the Translator for BC. It should be removed in 3.0.
     *
     * @param ConfigCacheFactoryInterface $configCacheFactory
     *
     * @internal
     */
    public function setConfigCacheFactory(ConfigCacheFactoryInterface $configCacheFactory)
    {
        $this->configCacheFactory = $configCacheFactory;
    }

    /**
     * Provides the ConfigCache factory implementation.
     *
     * This method is added because it needs in the Translator for BC. It should be removed in 3.0.
     *
     * @return ConfigCacheFactoryInterface $configCacheFactory
     *
     * @internal
     */
    public function getConfigCacheFactory()
    {
        return $this->configCacheFactory;
    }

    /**
     * This method is public because it needs to be callable from a closure in PHP 5.3. It should be made protected (or even private, if possible) in 3.0.
     *
     * @internal
     */
    public function dumpCatalogue($catalogue, ConfigCacheInterface $cache)
    {
        $fallbackContent = $this->getFallbackContent($catalogue);

        $content = sprintf(<<<EOF
<?php

use Symfony\Component\Translation\MessageCatalogue;

\$catalogue = new MessageCatalogue('%s', %s);

%s
return \$catalogue;

EOF
            ,
            $catalogue->getLocale(),
            var_export($catalogue->all(), true),
            $fallbackContent
        );

        $cache->write($content, $catalogue->getResources());
    }

    private function getFallbackContent(MessageCatalogue $catalogue)
    {
        $fallbackContent = '';
        $current = '';
        $replacementPattern = '/[^a-z0-9_]/i';
        $fallbackCatalogue = $catalogue->getFallbackCatalogue();
        while ($fallbackCatalogue) {
            $fallback = $fallbackCatalogue->getLocale();
            $fallbackSuffix = ucfirst(preg_replace($replacementPattern, '_', $fallback));
            $currentSuffix = ucfirst(preg_replace($replacementPattern, '_', $current));

            $fallbackContent .= sprintf(<<<EOF
\$catalogue%s = new MessageCatalogue('%s', %s);
\$catalogue%s->addFallbackCatalogue(\$catalogue%s);

EOF
                ,
                $fallbackSuffix,
                $fallback,
                var_export($fallbackCatalogue->all(), true),
                $currentSuffix,
                $fallbackSuffix
            );
            $current = $fallbackCatalogue->getLocale();
            $fallbackCatalogue = $fallbackCatalogue->getFallbackCatalogue();
        }

        return $fallbackContent;
    }

    /**
     * This method is public because it needs in the Translator for BC. It should be made private in 3.0.
     *
     * @internal
     */
    public function getCatalogueCachePath($locale)
    {
        if ($this->messageCatalogueProvider instanceof FallbackMessageCatalogueProvider) {
            return $this->cacheDir.'/catalogue.'.$locale.'.'.sha1(serialize($this->messageCatalogueProvider->getFallbackLocales())).'.php';
        }

        return $this->cacheDir.'/'.'catalogue.'.$locale.'.php';
    }
}
