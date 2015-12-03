<?php

namespace Oro\Bundle\EntityExtendBundle\Form\Type;

use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use Symfony\Component\Form\FormBuilderInterface;

class UniqueKeyType extends AbstractType
{
    /**
     * @var FieldConfigId[]
     */
    protected $fields;

    public function __construct($fields)
    {
        $this->fields = $fields;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $choices = array_map(
            function (FieldConfigId $field) {
                return ucfirst($field->getFieldName());
            },
            $this->fields
        );

        $choices = array_combine($choices, $choices);

        $builder->add(
            'name',
            TextType::class,
            array(
                'required' => true,
            )
        );

        $builder->add(
            'key',
            ChoiceType::class,
            array(
                'multiple' => true,
                'choices'  => $choices,
                'required' => true,
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'oro_entity_extend_unique_key_type';
    }
}
