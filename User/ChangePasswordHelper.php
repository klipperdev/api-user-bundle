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
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class ChangePasswordHelper
{
    private ControllerHelper $helper;

    private DomainManagerInterface $domainManager;

    private UserPasswordEncoderInterface $passwordEncoder;

    private TranslatorInterface $translator;

    public function __construct(
        ControllerHelper $helper,
        DomainManagerInterface $domainManager,
        UserPasswordEncoderInterface $passwordEncoder,
        TranslatorInterface $translator
    ) {
        $this->helper = $helper;
        $this->domainManager = $domainManager;
        $this->passwordEncoder = $passwordEncoder;
        $this->translator = $translator;
    }

    public function process(UserInterface $user): Response
    {
        $form = $this->helper->processForm(new FormConfig(ChangePasswordType::class), []);
        $oldPassword = $form->get('old_password')->getData();
        $newPassword = $form->get('new_password')->getData();

        if ($form->isValid() && !$this->passwordEncoder->isPasswordValid($user, $oldPassword)) {
            $form->get('old_password')->addError(new FormError(
                $this->translator->trans('This value is not valid.', [], 'validators')
            ));
        }

        if (!$form->isValid()) {
            return $this->helper->handleView($this->helper->createViewFormErrors($form));
        }

        $user->setPassword($this->passwordEncoder->encodePassword($user, $newPassword));
        $res = $this->domainManager->get(UserInterface::class)->update($user);

        if (!$res->isValid()) {
            throw new ConstraintViolationException($res->getErrors());
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
