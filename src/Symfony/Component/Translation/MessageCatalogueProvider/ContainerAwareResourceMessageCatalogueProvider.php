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

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lazily loads loaders from the dependency injection
 * container.
 *
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 */
class ContainerAwareResourceMessageCatalogueProvider extends ResourceMessageCatalogueProvider
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var array
     */
    private $loaderIds;

    /**
     * @var array
     */
    private $fileResources;

    /**
     * @param ContainerInterface $container     A ContainerInterface instance
     * @param array              $loaderIds
     * @param array              $fileResources
     */
    public function __construct(ContainerInterface $container, $loaderIds, $fileResources)
    {
        $this->container = $container;
        $this->loaderIds = $loaderIds;
        $this->fileResources = $fileResources;
    }

    /**
     * {@inheritdoc}
     */
    public function getLoaders()
    {
        foreach ($this->loaderIds as $id => $aliases) {
            foreach ($aliases as $alias) {
                $this->addLoader($alias, $this->container->get($id));
            }
        }

        return parent::getLoaders();
    }

    /**
     * @return array
     */
    public function getResources($locale)
    {
        foreach ($this->fileResources as $key => $resource) {
            if ($resource[2] === $locale) {
                $this->addResource($resource[0], $resource[1], $locale, isset($resource[3]) ? $resource[3] : null);
            }
            unset($this->resources[$key]);
        }

        return parent::getResources($locale);
    }
}
