<?php

namespace Trustify\Bundle\MassUpdateBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Oro\Bundle\EntityMergeBundle\EventListener\Metadata\EntityConfigHelper;

use Trustify\Bundle\MassUpdateBundle\Form\Guesser\RegularFieldTypeGuesser;

/**
 * The purpose of this form type is to prepare form for one field
 * with correct underlying form type (e.g. datetime for dates, etc)
 * except extended fields (enums, etc)
 * cause their types will be guessed by ExtendFieldTypeGuesser
 */
class GuessFieldType extends AbstractType
{
    const NAME = 'guess_field_type';

    /** @var EntityConfigHelper */
    protected $entityConfigHelper;

    /** @var RegularFieldTypeGuesser */
    protected $guesser;

    /** @var array */
    protected $mapping;

    public function __construct(
        EntityConfigHelper $entityConfigHelper,
        FormTypeGuesserInterface $guesser,
        array $mapping = []
    ) {
        $this->entityConfigHelper = $entityConfigHelper;
        $this->guesser = $guesser;
        $this->mapping = $mapping;
    }


    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (empty($options['data_class']) || empty($options['field_name'])) {
            throw new InvalidOptionsException(
                'Both "class_name" and "field_name" options must be set.'
            );
        }

        $className = $options['data_class'];
        $fieldName = $options['field_name'];

        if ($this->isExtendField($className, $fieldName)) {
            $type         = null; // guess it
            $fieldOptions = [];
        } else {
            // try to guess field type based on entity's field config
            list ($type, $fieldOptions) = $this->guessFieldType($className, $fieldName);
        }

        $builder->add($fieldName, $type, $fieldOptions);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['field_name' => null]);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return static::NAME;
    }

    /**
     * Guess field types only for this form type
     * for not extended fields
     * can't use regular guessers, as they will work for all forms
     *
     * @param string $className
     * @param string $fieldName
     *
     * @return array
     */
    protected function guessFieldType($className, $fieldName)
    {
        $type         = null;
        $fieldOptions = ['label' => ''];

        // chance to guess by names
        if (isset($this->mapping['all'][$fieldName])) {
            $map          = $this->mapping['all'][$fieldName];
            $type         = $map['type'];
            $fieldOptions = $map['options'];
        }

        // if more specific map rule exists
        if (isset($this->mapping[$className][$fieldName])) {
            $map          = $this->mapping[$className][$fieldName];
            $type         = $map['type'];
            $fieldOptions = $map['options'];
        }

        if (is_null($type)) {
            $guess = $this->guesser->guessType($className, $fieldName);
            if ($guess) {
                $type = $guess->getType();
                $fieldOptions = $guess->getOptions();
            }
        }

        return [$type, $fieldOptions];
    }

    /**
     * @param string $className
     * @param string $fieldName
     *
     * @return bool
     */
    protected function isExtendField($className, $fieldName)
    {
        $entityConfig = $this->entityConfigHelper->getConfig(
            EntityConfigHelper::EXTEND_CONFIG_SCOPE,
            $className,
            $fieldName
        );

        if ($entityConfig) {
            return $entityConfig->is('is_extend');
        } else {
            return false;
        }
    }
}
