<?php

/*
 * This file is part of the Klipper package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Bundle\ApiUserBundle\Controller;

use Klipper\Bundle\ApiBundle\Action\Create;
use Klipper\Bundle\ApiBundle\Controller\Action\Listener\FormPostSubmitListenerInterface;
use Klipper\Bundle\ApiBundle\Controller\ControllerHelper;
use Klipper\Bundle\ApiUserBundle\Form\Type\CreateOrganizationUserType;
use Klipper\Component\Security\Model\OrganizationUserInterface;
use Klipper\Component\SecurityOauth\Scope\ScopeVote;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class OrganizationUserController
{
    /**
     * Create a user for the organization.
     *
     * @Route("/organization_users/create", methods={"POST"})
     */
    public function create(
        ControllerHelper $helper,
        UserPasswordEncoderInterface $passwordEncoder
    ): Response {
        if (class_exists(ScopeVote::class)) {
            $helper->denyAccessUnlessGranted(new ScopeVote('meta/organization_user'));
        }

        return $helper->create(
            Create::build(
                CreateOrganizationUserType::class,
                OrganizationUserInterface::class
            )->addListener(static function (PostSubmitEvent $event) use ($passwordEncoder): void {
                /** @var OrganizationUserInterface $data */
                $data = $event->getData();

                if ($event->getForm()->isValid()) {
                    $data->getUser()->setPassword(
                        $passwordEncoder->encodePassword(
                            $data->getUser(),
                            $event->getForm()->get('user')->get('password')->getData()
                        )
                    );
                }
            }, FormPostSubmitListenerInterface::class)
        );
    }
}
