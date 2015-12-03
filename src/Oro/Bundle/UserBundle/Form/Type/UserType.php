<?php

namespace Oro\Bundle\UserBundle\Form\Type;

use Doctrine\ORM\EntityRepository;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Form\EventListener\UserSubscriber;
use Oro\Bundle\UserBundle\Form\Type\EmailType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;

use Symfony\Component\Form\FormView;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\SecurityContextInterface;

class UserType extends AbstractType
{
    /**
     * @var SecurityContextInterface
     */
    protected $security;

    /**
     * @var bool
     */
    protected $isMyProfilePage;

    /**
     * @param SecurityContextInterface $security        Security context
     * @param Request $request         Request
     */
    public function __construct(
        SecurityContextInterface $security,
        Request $request
    ) {

        $this->security = $security;
        if ($request->attributes->get('_route') == 'oro_user_profile_update') {
            $this->isMyProfilePage = true;
        } else {
            $this->isMyProfilePage = false;
        }
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->addEntityFields($builder);
    }


    /**
     * {@inheritdoc}
     */
    public function addEntityFields(FormBuilderInterface $builder)
    {
        // user fields
        $builder->addEventSubscriber(
            new UserSubscriber($builder->getFormFactory(), $this->security)
        );
        $this->setDefaultUserFields($builder);
        $builder
            ->add(
                'rolesCollection',
                'entity',
                array(
                    'label'          => 'Roles',
                    'class'          => 'OroUserBundle:Role',
                    'property'       => 'label',
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('r')
                            ->where('r.role <> :anon')
                            ->setParameter('anon', User::ROLE_ANONYMOUS);
                    },
                    'multiple'       => true,
                    'expanded'       => true,
                    'required'       => !$this->isMyProfilePage,
                    'read_only'      => $this->isMyProfilePage,
                    'disabled'      => $this->isMyProfilePage,
                )
            )
            ->add(
                'groups',
                'entity',
                array(
                    'class'          => 'OroUserBundle:Group',
                    'property'       => 'name',
                    'multiple'       => true,
                    'expanded'       => true,
                    'required'       => false,
                    'read_only'      => $this->isMyProfilePage,
                    'disabled'       => $this->isMyProfilePage
                )
            )
            ->add(
                'plainPassword',
                RepeatedType::class,
                array(
                    'type'           => 'password',
                    'required'       => true,
                    'first_options'  => array('label' => 'Password'),
                    'second_options' => array('label' => 'Re-enter password'),
                )
            )
            ->add(
                'emails',
                CollectionType::class,
                array(
                    'type'           => 'oro_user_email',
                    'allow_add'      => true,
                    'allow_delete'   => true,
                    'by_reference'   => false,
                    'prototype'      => true,
                    'prototype_name' => 'tag__name__',
                    'label'          => ' '
                )
            )
            ->add(
                'tags',
                'oro_tag_select'
            )
            ->add('imapConfiguration', 'oro_imap_configuration')
            ->add(
                'change_password',
                'oro_change_password'
            );
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            array(
                'data_class'           => 'Oro\Bundle\UserBundle\Entity\User',
                'intention'            => 'user',
                'validation_groups'    => function ($form) {
                    if ($form instanceof FormInterface) {
                        $user = $form->getData();
                    } elseif ($form instanceof FormView) {
                        $user = $form->vars['value'];
                    } else {
                        $user = null;
                    }

                    return $user && $user->getId()
                        ? array('User', 'Default')
                        : array('Registration', 'User', 'Default');
                },
                'extra_fields_message' => 'This form should not contain extra fields: "{{ extra_fields }}"',
                'error_mapping'        => array(
                    'roles' => 'rolesCollection'
                ),
                'cascade_validation'   => true
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'oro_user_user';
    }

    /**
     * Set user fields
     *
     * @param FormBuilderInterface $builder
     */
    protected function setDefaultUserFields(FormBuilderInterface $builder)
    {
        $builder
            ->add(
                'username',
                TextType::class,
                array(
                    'required'       => true,
                )
            )
            ->add(
                'email',
                EmailType::class,
                array(
                    'label'          => 'E-mail',
                    'required'       => true,
                )
            )
            ->add(
                'namePrefix',
                TextType::class,
                array(
                    'label'          => 'Name prefix',
                    'required'       => false,
                )
            )
            ->add(
                'firstName',
                TextType::class,
                array(
                    'label'          => 'First name',
                    'required'       => true,
                )
            )
            ->add(
                'middleName',
                TextType::class,
                array(
                    'label'          => 'Middle name',
                    'required'       => false,
                )
            )
            ->add(
                'lastName',
                TextType::class,
                array(
                     'label'          => 'Last name',
                     'required'       => true,
                )
            )
            ->add(
                'nameSuffix',
                TextType::class,
                array(
                    'label'          => 'Name suffix',
                    'required'       => false,
                )
            )
            ->add(
                'birthday',
                'oro_date',
                array(
                    'label'          => 'Date of birth',
                    'required'       => false,
                )
            )
            ->add(
                'imageFile',
                FileType::class,
                array(
                    'label'          => 'Avatar',
                    'required'       => false,
                )
            );
    }
}
