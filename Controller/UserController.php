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

use Klipper\Bundle\ApiBundle\Action\Update;
use Klipper\Bundle\ApiBundle\Controller\ControllerHelper;
use Klipper\Bundle\ApiBundle\Exception\InvalidArgumentException;
use Klipper\Bundle\ApiBundle\ViewGroups;
use Klipper\Bundle\ApiUserBundle\User\ChangePasswordHelper;
use Klipper\Component\Content\ContentManagerInterface;
use Klipper\Component\Metadata\MetadataManagerInterface;
use Klipper\Component\Security\Model\UserInterface;
use Klipper\Component\SecurityOauth\Scope\ScopeVote;
use Klipper\Component\User\Model\Traits\ProfileableInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class UserController
{
    /**
     * View the user info of current user.
     *
     * @Route("/user", methods={"GET"})
     */
    public function viewUser(
        ControllerHelper $helper,
        TokenStorageInterface $tokenStorage
    ): Response {
        if (class_exists(ScopeVote::class)) {
            $helper->denyAccessUnlessGranted(new ScopeVote(['meta/user', 'meta/user.readonly'], false));
        }

        $helper->createView()->getContext()->addGroup(ViewGroups::CURRENT_USER);

        return $helper->view($this->getCurrentUser($helper, $tokenStorage));
    }

    /**
     * Update the user info of current user.
     *
     * @Route("/user", methods={"PATCH"})
     */
    public function updateAction(
        ControllerHelper $helper,
        TokenStorageInterface $tokenStorage,
        MetadataManagerInterface $metadataManager
    ): Response {
        if (class_exists(ScopeVote::class)) {
            $helper->denyAccessUnlessGranted(new ScopeVote('meta/user'));
        }

        $meta = $metadataManager->get(UserInterface::class);

        if (null === $formType = $meta->getFormType()) {
            throw new InvalidArgumentException(sprintf(
                'The metadata form type of the "%s" class is required to edit the user',
                $meta->getClass()
            ));
        }

        $helper->createView()->getContext()->addGroup(ViewGroups::CURRENT_USER);

        return $helper->update(Update::build(
            $formType,
            $this->getCurrentUser($helper, $tokenStorage)
        ));
    }

    /**
     * Update the password of current user.
     *
     * @Route("/user/change-password", methods={"PATCH"})
     */
    public function changePassword(
        ControllerHelper $helper,
        TokenStorageInterface $tokenStorage,
        ChangePasswordHelper $changePasswordHelper
    ): Response {
        if (class_exists(ScopeVote::class)) {
            $helper->denyAccessUnlessGranted(new ScopeVote('meta/user'));
        }

        return $changePasswordHelper->process($this->getCurrentUser($helper, $tokenStorage));
    }

    /**
     * Upload the image of current user.
     *
     * @Route("/user/upload", methods={"POST"})
     */
    public function uploadImage(
        ControllerHelper $helper,
        ContentManagerInterface $contentManager,
        TokenStorageInterface $tokenStorage
    ): Response {
        if (class_exists(ScopeVote::class)) {
            $helper->denyAccessUnlessGranted(new ScopeVote('meta/user'));
        }

        $user = $this->getCurrentUser($helper, $tokenStorage);

        return $contentManager->upload('user_image', $user);
    }

    /**
     * Download the user image of current user.
     *
     * @Route("/user.{ext}", methods={"GET"})
     */
    public function downloadImage(
        ControllerHelper $helper,
        TokenStorageInterface $tokenStorage,
        ContentManagerInterface $contentManager
    ): Response {
        $user = $this->getCurrentUser($helper, $tokenStorage);

        if (class_exists(ScopeVote::class)) {
            $helper->denyAccessUnlessGranted(new ScopeVote(['meta/user', 'meta/user.readonly'], false));
        }

        return $contentManager->downloadImage(
            'user_image',
            $user->getImagePath(),
            $user->getFullName() ?? $user->getUsername()
        );
    }

    /**
     * @return ProfileableInterface|UserInterface
     */
    private function getCurrentUser(
        ControllerHelper $helper,
        TokenStorageInterface $tokenStorage
    ): UserInterface {
        $token = $tokenStorage->getToken();
        $user = null !== $token ? $token->getUser() : null;

        if (!$user instanceof UserInterface
                || !$user instanceof ProfileableInterface) {
            throw $helper->createNotFoundException();
        }

        return $user;
    }
}
