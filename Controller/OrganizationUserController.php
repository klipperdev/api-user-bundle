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
use Klipper\Bundle\ApiBundle\Action\Update;
use Klipper\Bundle\ApiBundle\Controller\Action\Listener\FormPostSubmitListenerInterface;
use Klipper\Bundle\ApiBundle\Controller\ControllerHelper;
use Klipper\Bundle\ApiBundle\Exception\InvalidArgumentException;
use Klipper\Bundle\ApiUserBundle\Form\Type\CreateOrganizationUserType;
use Klipper\Bundle\ApiUserBundle\User\ChangePasswordHelper;
use Klipper\Component\Content\ContentManagerInterface;
use Klipper\Component\Metadata\MetadataManagerInterface;
use Klipper\Component\Security\Model\OrganizationUserInterface;
use Klipper\Component\Security\Model\UserInterface;
use Klipper\Component\SecurityOauth\Scope\ScopeVote;
use Klipper\Component\User\Model\Traits\ProfileableInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
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
     *
     * @Security("is_granted('perm:create', 'App\\Entity\\OrganizationUser')")
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

    /**
     * Update a user for the organization.
     *
     * @Entity(
     *     "id",
     *     class="App:OrganizationUser",
     *     expr="repository.findOrganizationUserById(id)"
     * )
     *
     * @Route("/organization_users/{id}/user", methods={"PATCH"})
     *
     * @Security("is_granted('perm:update', id)")
     */
    public function updateUser(
        ControllerHelper $helper,
        MetadataManagerInterface $metadataManager,
        OrganizationUserInterface $id
    ): Response {
        if (class_exists(ScopeVote::class)) {
            $helper->denyAccessUnlessGranted(new ScopeVote('meta/organization_user'));
        }

        $meta = $metadataManager->get(UserInterface::class);

        if (null === $formType = $meta->getFormType()) {
            throw new InvalidArgumentException(sprintf(
                'The metadata form type of the "%s" class is required to edit the user info of organization user',
                $meta->getClass()
            ));
        }

        return $helper->update(Update::build(
            $formType,
            $id->getUser()
        ));
    }

    /**
     * Change the password of a organization user.
     *
     * @Entity(
     *     "id",
     *     class="App:OrganizationUser",
     *     expr="repository.findOrganizationUserById(id)"
     * )
     *
     * @Route("/organization_users/{id}/change-password", methods={"PATCH"})
     *
     * @Security("is_granted('perm:update', id)")
     */
    public function changePassword(
        ControllerHelper $helper,
        ChangePasswordHelper $changePasswordHelper,
        OrganizationUserInterface $id
    ): Response {
        if (class_exists(ScopeVote::class)) {
            $helper->denyAccessUnlessGranted(new ScopeVote('meta/organization_user'));
        }

        $user = $id->getUser();

        if (!$user instanceof ProfileableInterface) {
            throw $helper->createNotFoundException();
        }

        return $changePasswordHelper->process($user, false);
    }

    /**
     * Upload a user image for the organization user.
     *
     * @Entity(
     *     "id",
     *     class="App:OrganizationUser",
     *     expr="repository.findOrganizationUserById(id)"
     * )
     *
     * @Route("/organization_users/{id}/user/upload", methods={"POST"})
     *
     * @Security("is_granted('perm:update', id)")
     */
    public function uploadImage(
        ControllerHelper $helper,
        ContentManagerInterface $contentManager,
        OrganizationUserInterface $id
    ): Response {
        if (class_exists(ScopeVote::class)) {
            $helper->denyAccessUnlessGranted(new ScopeVote('meta/organization_user'));
        }

        $user = $id->getUser();

        if (!$user instanceof ProfileableInterface) {
            throw $helper->createNotFoundException();
        }

        return $contentManager->upload('user_image', $user);
    }
}
