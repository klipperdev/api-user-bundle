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
use Klipper\Component\Security\Model\UserInterface;
use Klipper\Component\User\Model\ProfileInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class CreateUserType extends AbstractType
{
    private MetadataManagerInterface $metadataManager;

    public function __construct(MetadataManagerInterface $metadataManager)
    {
        $this->metadataManager = $metadataManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $profileMeta = $this->metadataManager->get(ProfileInterface::class);

        if (null === $profileMeta->getFormType()) {
            throw new InvalidArgumentException('The form type must be defined in the metadata of profile model');
        }

        if (!$builder->has('password')) {
            $builder->add('password', PasswordType::class, [
                'mapped' => false,
                'constraints' => [
                    new NotBlank(),
                    new Length([
                        'min' => 6,
                        'max' => 4096,
                    ]),
                ],
            ]);
        }

        $builder->add('profile', $profileMeta->getFormType(), $profileMeta->getFormOptions());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserInterface::class,
        ]);
    }

    public function getParent(): string
    {
        return ObjectMetadataType::class;
    }
}
