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
use Klipper\Component\Metadata\MetadataManagerInterface;
use Klipper\Component\Resource\Domain\DomainManagerInterface;
use Klipper\Component\Security\Model\OrganizationUserInterface;
use Klipper\Component\Security\Model\UserInterface;
use Klipper\Component\Security\Organizational\OrganizationalContextInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Valid;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class CreateOrganizationUserType extends AbstractType
{
    private OrganizationalContextInterface $orgContext;

    private DomainManagerInterface $domainManager;

    private MetadataManagerInterface $metadataManager;

    public function __construct(
        OrganizationalContextInterface $orgContext,
        DomainManagerInterface $domainManager,
        MetadataManagerInterface $metadataManager
    ) {
        $this->orgContext = $orgContext;
        $this->domainManager = $domainManager;
        $this->metadataManager = $metadataManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var OrganizationUserInterface $data */
        $data = $builder->getData();
        $this->addOrganization($data);
        $this->addUser($data);

        $userMeta = $this->metadataManager->get(UserInterface::class);

        if (null === $userMeta->getFormType()) {
            throw new InvalidArgumentException('The form type must be defined in the metadata of user model');
        }

        $builder->add('user', CreateUserType::class, array_merge($userMeta->getFormOptions(), [
            'constraints' => [
                new Valid(),
            ],
        ]));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OrganizationUserInterface::class,
        ]);
    }

    public function getParent(): string
    {
        return ObjectMetadataType::class;
    }

    private function addOrganization(OrganizationUserInterface $data): void
    {
        if (null === $data->getOrganization()) {
            $data->setOrganization($this->orgContext->getCurrentOrganization());
        }
    }

    private function addUser(OrganizationUserInterface $data): void
    {
        if (null === $data->getUser()) {
            /** @var UserInterface $user */
            $user = $this->domainManager->get(UserInterface::class)->newInstance();
            $data->setUser($user);
        }
    }
}
