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

use Klipper\Bundle\ApiBundle\Action\Delete;
use Klipper\Bundle\ApiBundle\Action\Update;
use Klipper\Bundle\ApiBundle\Controller\ControllerHelper;
use Klipper\Bundle\ApiBundle\Exception\InvalidArgumentException;
use Klipper\Bundle\ApiBundle\ViewGroups;
use Klipper\Component\Content\ContentManagerInterface;
use Klipper\Component\Metadata\MetadataManagerInterface;
use Klipper\Component\Security\Model\OrganizationInterface;
use Klipper\Component\Security\Organizational\OrganizationalContextInterface;
use Klipper\Component\SecurityOauth\Scope\ScopeVote;
use Klipper\Contracts\Model\ImagePathInterface;
use Klipper\Contracts\Model\LabelableInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class OrganizationController
{
    /**
     * View the current organization.
     *
     * @Route("/organization", methods={"GET"})
     */
    public function viewOrganization(
        ControllerHelper $helper,
        OrganizationalContextInterface $orgContext
    ): Response {
        if (class_exists(ScopeVote::class)) {
            $helper->denyAccessUnlessGranted(new ScopeVote(['meta/organization', 'meta/organization.readonly'], false));
        }

        $helper->createView()->getContext()->addGroup(ViewGroups::CURRENT_USER);

        return $helper->view($this->getCurrentOrganization($helper, $orgContext));
    }

    /**
     * Update the current organization.
     *
     * @Route("/organization", methods={"PATCH"})
     */
    public function updateAction(
        ControllerHelper $helper,
        OrganizationalContextInterface $orgContext,
        MetadataManagerInterface $metadataManager
    ): Response {
        if (class_exists(ScopeVote::class)) {
            $helper->denyAccessUnlessGranted(new ScopeVote('meta/organization'));
        }

        $meta = $metadataManager->get(OrganizationInterface::class);

        if (null === $formType = $meta->getFormType()) {
            throw new InvalidArgumentException(sprintf(
                'The metadata form type of the "%s" class is required to edit the organization',
                $meta->getClass()
            ));
        }

        $helper->createView()->getContext()->addGroup(ViewGroups::CURRENT_USER);

        return $helper->update(Update::build(
            $formType,
            $this->getCurrentOrganization($helper, $orgContext)
        ));
    }

    /**
     * Upload the image of current organization.
     *
     * @Route("/organization/upload", methods={"POST"})
     */
    public function uploadImage(
        ControllerHelper $helper,
        ContentManagerInterface $contentManager,
        OrganizationalContextInterface $orgContext
    ): Response {
        if (class_exists(ScopeVote::class)) {
            $helper->denyAccessUnlessGranted(new ScopeVote('meta/user'));
        }

        $organization = $this->getCurrentOrganization($helper, $orgContext);

        return $contentManager->upload('organization_image', $organization);
    }

    /**
     * Download the image of current organization.
     *
     * @Route("/organization.{ext}", methods={"GET"})
     */
    public function downloadImage(
        ControllerHelper $helper,
        OrganizationalContextInterface $orgContext,
        ContentManagerInterface $contentManager
    ): Response {
        $organization = $this->getCurrentOrganization($helper, $orgContext);

        if (!$organization instanceof ImagePathInterface) {
            throw $helper->createNotFoundException();
        }

        if (class_exists(ScopeVote::class)) {
            $helper->denyAccessUnlessGranted(new ScopeVote(['meta/organization', 'meta/organization.readonly'], false));
        }

        return $contentManager->downloadImage(
            'organization_image',
            $organization->getImagePath(),
            $organization instanceof LabelableInterface
                ? $organization->getLabel()
                : $organization->getName()
        );
    }

    /**
     * Delete the current organization and all related data.
     *
     * @Route("/organization", methods={"DELETE"})
     */
    public function deleteAction(
        ControllerHelper $helper,
        OrganizationalContextInterface $orgContext
    ): Response {
        if (class_exists(ScopeVote::class)) {
            $helper->denyAccessUnlessGranted(new ScopeVote('meta/organization'));
        }

        return $helper->delete(Delete::build($this->getCurrentOrganization($helper, $orgContext)));
    }

    /**
     * Get the current organization.
     */
    private function getCurrentOrganization(
        ControllerHelper $helper,
        OrganizationalContextInterface $orgContext
    ): OrganizationInterface {
        $organization = $orgContext->getCurrentOrganization();

        if (null === $organization) {
            throw $helper->createNotFoundException();
        }

        return $organization;
    }
}
