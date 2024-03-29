<?php

/*
 * This file is part of the Klipper package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Bundle\ApiUserBundle\DependencyInjection;

use Klipper\Bundle\ApiBundle\Util\ControllerDefinitionUtil;
use Klipper\Bundle\ApiUserBundle\Controller\ConnectedUserController;
use Klipper\Bundle\ApiUserBundle\Controller\OrganizationController;
use Klipper\Bundle\ApiUserBundle\Controller\OrganizationUserController;
use Klipper\Bundle\ApiUserBundle\Controller\PublicUserController;
use Klipper\Bundle\ApiUserBundle\Controller\UserController;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class KlipperApiUserExtension extends Extension
{
    /**
     * @throws
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('change_password_helper.xml');
        $loader->load('upload_listener.xml');
        $loader->load('form.xml');
        $loader->load('doctrine_delete_content_config.xml');

        ControllerDefinitionUtil::set($container, UserController::class);
        ControllerDefinitionUtil::set($container, OrganizationController::class);
        ControllerDefinitionUtil::set($container, OrganizationUserController::class);
        ControllerDefinitionUtil::set($container, ConnectedUserController::class);
        ControllerDefinitionUtil::set($container, PublicUserController::class);
    }
}
