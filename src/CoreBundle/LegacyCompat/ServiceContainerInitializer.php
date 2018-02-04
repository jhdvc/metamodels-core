<?php

/**
 * This file is part of MetaModels/core.
 *
 * (c) 2012-2018 The MetaModels team.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    MetaModels
 * @subpackage Core
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @copyright  2012-2018 The MetaModels team.
 * @license    https://github.com/MetaModels/core/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

namespace MetaModels\CoreBundle\LegacyCompat;

use MetaModels\Filter\Setting\CustomSqlFilterSettingTypeFactory;
use MetaModels\MetaModelsServiceContainer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This class is the configurator for the legacy service container.
 *
 * @deprecated Only here as legacy gateway for MetaModels 2.0.
 */
class ServiceContainerInitializer
{
    /**
     * The container.
     *
     * @var ContainerInterface
     */
    private $container;

    /**
     * Create a new instance.
     *
     * @param ContainerInterface $container The container.
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Configure the legacy service container.
     *
     * @param MetaModelsServiceContainer $serviceContainer The container to configure.
     *
     * @return MetaModelsServiceContainer
     */
    public function configure(MetaModelsServiceContainer $serviceContainer)
    {
        $serviceContainer
            ->setEventDispatcher(function () {
                return $this->container->get('event_dispatcher');
            })
            ->setDatabase(function () {
                return $this->container->get('cca.legacy_dic.contao_database_connection');
            })
            ->setAttributeFactory(function () {
                return $this->container->get('metamodels.attribute_factory');
            })
            ->setFactory(function () {
                return $this->container->get('metamodels.factory');
            })
            ->setFilterFactory(function () {
                return $this->container->get('metamodels.filter_setting_factory');
            })
            ->setRenderSettingFactory(function () {
                return $this->container->get('metamodels.render_setting_factory');
            })
            ->setCache(function () {
                return $this->container->get('metamodels.cache');
            });

        return $serviceContainer;
    }

    /**
     * Configure the custom SQL filter factory to get the legacy service container.
     *
     * @param CustomSqlFilterSettingTypeFactory $factory The factory to configure.
     *
     * @return void
     */
    public function configureCustomSqlFactory(CustomSqlFilterSettingTypeFactory $factory)
    {
        $factory->setLegacyDic(function () {
            return $this->container->get('MetaModels\MetaModelsServiceContainer');
        });
    }
}
