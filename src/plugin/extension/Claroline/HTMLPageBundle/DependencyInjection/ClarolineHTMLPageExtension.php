<?php

namespace Claroline\HTMLPageBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Claroline\CoreBundle\Library\Plugin\PluginDependencyInjection;

class ClarolineHTMLPageExtension extends PluginDependencyInjection
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $locator = new FileLocator(__DIR__ . '/../Resources/config/services');
        $loader = new YamlFileLoader($container, $locator);
        $loader->load('services.yml');
        $loader->load('listeners.yml');

        parent::setUp($container);
    }
}