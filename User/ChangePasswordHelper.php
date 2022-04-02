<?php

/*
 * This file is part of the Klipper package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Bundle\ApiUserBundle\User;

use Klipper\Bundle\ApiBundle\Controller\ControllerHelper;
use Klipper\Bundle\ApiUserBundle\Form\Type\ChangePasswordType;
use Klipper\Component\Resource\Domain\DomainManagerInterface;
use Klipper\Component\Resource\Exception\ConstraintViolationException;
use Klipper\Component\Resource\Handler\FormConfig;
use Klipper\Component\Security\Model\UserInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class ChangePasswordHelper
{
    private ControllerHelper $helper;

    private DomainManagerInterface $domainManager;

    private UserPasswordHasherInterface $passwordHasher;

    private TranslatorInterface $translator;

    public function __construct(
        ControllerHelper $helper,
        DomainManagerInterface $domainManager,
        UserPasswordHasherInterface $passwordHasher,
        TranslatorInterface $translator
    ) {
        $this->helper = $helper;
        $this->domainManager = $domainManager;
        $this->passwordHasher = $passwordHasher;
        $this->translator = $translator;
    }

    /**
     * @param PasswordAuthenticatedUserInterface|UserInterface $user
     */
    public function process(UserInterface $user, bool $withOldPassword = true): Response
    {
        $form = $this->helper->processForm(new FormConfig(ChangePasswordType::class, ['old_password' => $withOldPassword]), []);
        $oldPassword = $withOldPassword ? $form->get('old_password')->getData() : null;
        $newPassword = $form->get('new_password')->getData();

        if ($form->isValid() && $withOldPassword && !$this->passwordHasher->isPasswordValid($user, $oldPassword)) {
            $form->get('old_password')->addError(new FormError(
                $this->translator->trans('This value is not valid.', [], 'validators')
            ));
        }

        if (!$form->isValid()) {
            return $this->helper->handleView($this->helper->createViewFormErrors($form));
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $res = $this->domainManager->get(UserInterface::class)->update($user);

        if (!$res->isValid()) {
            throw new ConstraintViolationException($res->getErrors());
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
