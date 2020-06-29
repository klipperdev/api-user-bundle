<?php

/*
 * This file is part of the Klipper package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Bundle\ApiUserBundle\Form\Type;

use Klipper\Bundle\ApiBundle\Form\Type\ObjectMetadataType;
use Klipper\Component\Form\Doctrine\Type\EntityType;
use Klipper\Component\Security\Model\OrganizationUserInterface;
use Klipper\Component\Security\Model\UserInterface;
use Klipper\Component\Security\Organizational\OrganizationalContextInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class OrganizationUserType extends AbstractType
{
    private OrganizationalContextInterface $orgContext;

    public function __construct(OrganizationalContextInterface $orgContext)
    {
        $this->orgContext = $orgContext;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data = $builder->getData();

        if (!$data instanceof OrganizationUserInterface || null !== $data->getId()) {
            return;
        }

        if (null === $data->getOrganization()) {
            $data->setOrganization($this->orgContext->getCurrentOrganization());
        }

        $builder->add('username', EntityType::class, [
            'class' => UserInterface::class,
            'property_path' => 'user',
            'choice_value' => 'username',
            'required' => true,
        ]);
    }

    public function getParent(): string
    {
        return ObjectMetadataType::class;
    }
}
