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

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Klipper\Bundle\ApiBundle\Controller\ControllerHelper;
use Klipper\Component\Content\ContentManagerInterface;
use Klipper\Component\DoctrineExtensions\Util\SqlFilterUtil;
use Klipper\Component\Resource\Domain\DomainManagerInterface;
use Klipper\Component\Security\Model\UserInterface;
use Klipper\Component\SecurityOauth\Scope\ScopeVote;
use Klipper\Component\User\Model\Traits\ProfileableInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class ConnectedUserController
{
    /**
     * Get the connected users.
     *
     * @Route("/connected_users", methods={"GET"})
     */
    public function listAction(
        ControllerHelper $helper,
        TokenStorageInterface $tokenStorage,
        DomainManagerInterface $domainManager,
        EntityManagerInterface $em
    ): Response {
        if (class_exists(ScopeVote::class)) {
            $helper->denyAccessUnlessGranted(new ScopeVote(['meta/organization_user', 'meta/organization_user.readonly'], false));
        }

        $user = $this->getCurrentUser($helper, $tokenStorage);
        SqlFilterUtil::disableFilters($em, ['userable', 'organization', 'organization_user']);
        $repo = $domainManager->get(UserInterface::class)->getRepository();
        $qb = $repo->createQueryBuilder('u')
            ->select('u')
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->addOrderBy('u.username', 'ASC')
        ;

        return $helper->views($this->addWhereConnectedFilter($qb, $user));
    }

    /**
     * Show a connected user.
     *
     * @param int|string $id
     *
     * @Route("/connected_users/{id}", methods={"GET"})
     */
    public function showAction(
        ControllerHelper $helper,
        TokenStorageInterface $tokenStorage,
        DomainManagerInterface $domainManager,
        EntityManagerInterface $em,
        $id
    ): Response {
        return $helper->view($this->getSelectedUser($helper, $tokenStorage, $domainManager, $em, $id));
    }

    /**
     * Show a connected user.
     *
     * @param int|string $id
     *
     * @Route("/connected_users/{id}/user.{ext}", methods={"GET"})
     */
    public function downloadProfileImage(
        ControllerHelper $helper,
        TokenStorageInterface $tokenStorage,
        DomainManagerInterface $domainManager,
        EntityManagerInterface $em,
        ContentManagerInterface $contentManager,
        $id
    ): Response {
        $user = $this->getSelectedUser($helper, $tokenStorage, $domainManager, $em, $id);

        return $contentManager->downloadImage(
            'user_image',
            $user->getImagePath(),
            $user->getFullName() ?? $user->getUserIdentifier()
        );
    }

    /**
     * Get the selected user.
     *
     * @param int|string $id
     *
     * @return ProfileableInterface|UserInterface
     *
     * @throws NotFoundHttpException
     * @throws AccessDeniedException
     */
    public function getSelectedUser(
        ControllerHelper $helper,
        TokenStorageInterface $tokenStorage,
        DomainManagerInterface $domainManager,
        EntityManagerInterface $em,
        $id
    ): UserInterface {
        if (class_exists(ScopeVote::class)) {
            $helper->denyAccessUnlessGranted(new ScopeVote(['meta/organization_user', 'meta/organization_user.readonly'], false));
        }

        $user = $this->getCurrentUser($helper, $tokenStorage);
        SqlFilterUtil::disableFilters($em, ['userable', 'organization', 'organization_user']);
        $repo = $domainManager->get(UserInterface::class)->getRepository();
        $qb = $repo->createQueryBuilder('u')
            ->select('u')
            ->where('u.id = :userId')
            ->setMaxResults(1)
            ->setParameter('userId', $id)
        ;

        $user = $this->addWhereConnectedFilter($qb, $user)->getQuery()->getOneOrNullResult();

        if (null === $user || !$user instanceof ProfileableInterface) {
            throw $helper->createNotFoundException();
        }

        return $user;
    }

    private function addWhereConnectedFilter(QueryBuilder $qb, UserInterface $user): QueryBuilder
    {
        return $qb
            ->andWhere('
                u.id IN (
                    SELECT DISTINCT IDENTITY(sub_uo.user)
                    FROM Klipper\Component\Security\Model\OrganizationUserInterface sub_uo
                    WHERE sub_uo.organization IN (
                        SELECT sub_o.id
                        FROM Klipper\Component\Security\Model\OrganizationInterface sub_o
                        WHERE sub_o.user IS NULL AND sub_o.id IN (
                            SELECT DISTINCT IDENTITY(sub2_uo.organization)
                            FROM Klipper\Component\Security\Model\OrganizationUserInterface sub2_uo
                            WHERE sub2_uo.user = :currentUser
                        )
                    )
                )
            ')
            ->setParameter('currentUser', $user)
        ;
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
