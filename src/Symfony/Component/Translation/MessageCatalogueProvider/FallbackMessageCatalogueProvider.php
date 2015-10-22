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

use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Translator;

/**
 * Merges the fallback catalogues into the loaded one.
 *
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 */
class FallbackMessageCatalogueProvider implements MessageCatalogueProviderInterface
{
    /**
     * @var MessageCatalogueProviderInterface
     */
    private $messageCatalogueProvider;

    /**
     * @var array
     */
    private $locales;

    /**
     * @var array
     */
    private $isFresh = array();

    /**
     * @param MessageCatalogueProviderInterface $messageCatalogueProvider The catalogue bag to use for loading the catalogue.
     * @param array                             $locales                  The fallback locales.
     */
    public function __construct(MessageCatalogueProviderInterface $messageCatalogueProvider, $locales = array())
    {
        $this->setFallbackLocales($locales);
        $this->messageCatalogueProvider = $messageCatalogueProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function getCatalogue($locale)
    {
        $catalogue = $this->messageCatalogueProvider->getCatalogue($locale);
        $this->loadFallbackCatalogues($catalogue);

        $this->isFresh[$locale] = true;

        return $catalogue;
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh($locale)
    {
        return $this->messageCatalogueProvider->isFresh($locale) && (isset($this->isFresh[$locale]) ? $this->isFresh[$locale] : true);
    }

    /**
     * Sets the fallback locales.
     *
     * @param array $locales The fallback locales
     */
    public function setFallbackLocales(array $locales)
    {
        foreach ($locales as $locale) {
            Translator::assertLocale($locale);
        }

        $this->fallbackLocales = $locales;

        // needed as the fallback locales are linked to the already loaded catalogues
        foreach ($this->isFresh as $locale => $value) {
            $this->isFresh[$locale] = false;
        }
    }

    /**
     * Gets the fallback locales.
     *
     * @return array $locales The fallback locales
     */
    public function getFallbackLocales()
    {
        return $this->fallbackLocales;
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

    private function loadFallbackCatalogues($catalogue)
    {
        $current = $catalogue;
        foreach ($this->computeFallbackLocales($catalogue->getLocale()) as $fallback) {
            $catalogue = $this->messageCatalogueProvider->getCatalogue($fallback);

            $fallbackCatalogue = new MessageCatalogue($fallback, $catalogue->all());
            $current->addFallbackCatalogue($fallbackCatalogue);
            $current = $fallbackCatalogue;
        }
    }

    /**
     * This method is public because it needs in the Translator for BC. It should be made private in 3.0.
     *
     * @internal
     */
    public function computeFallbackLocales($locale)
    {
        $locales = array();
        foreach ($this->fallbackLocales as $fallback) {
            if ($fallback === $locale) {
                continue;
            }

            $locales[] = $fallback;
        }

        if (strrchr($locale, '_') !== false) {
            array_unshift($locales, substr($locale, 0, -strlen(strrchr($locale, '_'))));
        }

        return array_unique($locales);
    }
}
