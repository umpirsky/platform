<?php

namespace Oro\Bundle\LocaleBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Intl\Intl;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CurrencyType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'choices' => Intl::getCurrencyBundle()->getCurrencyNames('en'),
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return CurrencyType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'oro_currency';
    }
}
