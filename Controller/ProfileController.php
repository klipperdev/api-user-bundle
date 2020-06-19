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
use Klipper\Component\Content\ContentManagerInterface;
use Klipper\Component\Metadata\MetadataManagerInterface;
use Klipper\Component\Security\Model\UserInterface;
use Klipper\Component\SecurityOauth\Scope\ScopeVote;
use Klipper\Component\User\Model\ProfileInterface;
use Klipper\Component\User\Model\Traits\ProfileableInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class ProfileController
{
    /**
     * View the profile of current user.
     *
     * @Route("/profile", methods={"GET"})
     */
    public function viewProfile(
        ControllerHelper $helper,
        TokenStorageInterface $tokenStorage
    ): Response {
        if (class_exists(ScopeVote::class)) {
            $helper->denyAccessUnlessGranted(new ScopeVote(['meta/user', 'meta/user.readonly'], false));
        }

        $helper->createView()->getContext()->addGroup(ViewGroups::CURRENT_USER);

        return $helper->view($this->getCurrentProfile($helper, $tokenStorage));
    }

    /**
     * Update the profile of current user.
     *
     * @Route("/profile", methods={"PATCH"})
     */
    public function updateAction(
        ControllerHelper $helper,
        TokenStorageInterface $tokenStorage,
        MetadataManagerInterface $metadataManager
    ): Response {
        if (class_exists(ScopeVote::class)) {
            $helper->denyAccessUnlessGranted(new ScopeVote('meta/user'));
        }

        $meta = $metadataManager->get(ProfileInterface::class);

        if (null === $formType = $meta->getFormType()) {
            throw new InvalidArgumentException(sprintf(
                'The metadata form type of the "%s" class is required to edit the profile',
                $meta->getClass()
            ));
        }

        $helper->createView()->getContext()->addGroup(ViewGroups::CURRENT_USER);

        return $helper->update(Update::build(
            $formType,
            $this->getCurrentProfile($helper, $tokenStorage)
        ));
    }

    /**
     * Upload the profile image of current user.
     *
     * @Route("/profile/upload", methods={"POST"})
     */
    public function uploadImage(
        ControllerHelper $helper,
        ContentManagerInterface $contentManager,
        TokenStorageInterface $tokenStorage
    ): Response {
        if (class_exists(ScopeVote::class)) {
            $helper->denyAccessUnlessGranted(new ScopeVote('meta/user'));
        }

        $profile = $this->getCurrentProfile($helper, $tokenStorage);

        return $contentManager->upload('user_profile_image', $profile);
    }

    /**
     * Download the profile image of current user.
     *
     * @Route("/profile.{ext}", methods={"GET"})
     */
    public function downloadImage(
        ControllerHelper $helper,
        TokenStorageInterface $tokenStorage,
        ContentManagerInterface $contentManager
    ): Response {
        $profile = $this->getCurrentProfile($helper, $tokenStorage);

        if (class_exists(ScopeVote::class)) {
            $helper->denyAccessUnlessGranted(new ScopeVote(['meta/user', 'meta/user.readonly'], false));
        }

        return $contentManager->downloadImage(
            'user_profile_image',
            $profile->getImagePath(),
            $profile->getFullName() ?? $profile->getUsername()
        );
    }

    /**
     * Get the current profile.
     */
    private function getCurrentProfile(
        ControllerHelper $helper,
        TokenStorageInterface $tokenStorage
    ): ProfileInterface {
        $token = $tokenStorage->getToken();
        $user = null !== $token ? $token->getUser() : null;

        if (!$user instanceof UserInterface
            || !$user instanceof ProfileableInterface
            || null === $user->getProfile()) {
            throw $helper->createNotFoundException();
        }

        return $user->getProfile();
    }
}
