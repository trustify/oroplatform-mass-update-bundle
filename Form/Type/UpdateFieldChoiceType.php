<?php

namespace Trustify\Bundle\MassUpdateBundle\Form\Type;

use Oro\Bundle\EntityBundle\Form\Type\EntityFieldChoiceType;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProviderInterface;
use Oro\Bundle\EntityExtendBundle\Extend\RelationType;

class UpdateFieldChoiceType extends EntityFieldChoiceType
{
    const NAME = 'update_field_choice';

    /** @var ConfigProviderInterface */
    protected $formConfigProvider;

    /** @var DoctrineHelper */
    protected $doctrineHelper;

    /**
     * @param ConfigProviderInterface $formConfigProvider
     */
    public function setFormConfigProvider(ConfigProviderInterface $formConfigProvider)
    {
        $this->formConfigProvider = $formConfigProvider;
    }

    /**
     * @param DoctrineHelper $doctrineHelper
     */
    public function setDoctrineHelper(DoctrineHelper $doctrineHelper)
    {
        $this->doctrineHelper = $doctrineHelper;
    }

    /**
     * {@inheritdoc}
     */
    protected function getChoices($entityName, $withRelations, $withVirtualFields)
    {
        $choiceFields    = [];
        $choiceRelations = [];

        $idFieldNames = $this->doctrineHelper->getEntityIdentifierFieldNames($entityName);
        foreach ($this->getEntityFields($entityName, $withRelations, $withVirtualFields) as $fieldName => $field) {
            $formConfig = $this->formConfigProvider->getConfig($entityName, $fieldName);

            if ($formConfig->is('is_enabled', false) || in_array($fieldName, $idFieldNames)) {
                // field is not enabled for displaying in forms
                // or field is entity identifier

                continue;
            }

            if (!isset($field['relation_type'])) {
                $choiceFields[$fieldName] = $field['label'];
            } elseif (!in_array($field['relation_type'], RelationType::$toManyRelations)) {
                // enable only mass update for *-to-one relations
                $choiceRelations[$fieldName] = $field['label'];
            }
        }

        if (empty($choiceRelations)) {
            return $choiceFields;
        }

        $choices = [];
        if (!empty($choiceFields)) {
            $choices[$this->translator->trans('oro.entity.form.entity_fields')] = $choiceFields;
        }
        $choices[$this->translator->trans('oro.entity.form.entity_related')] = $choiceRelations;

        return $choices;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return self::NAME;
    }
}
