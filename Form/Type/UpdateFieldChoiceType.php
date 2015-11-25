<?php

namespace Trustify\Bundle\MassUpdateBundle\Form\Type;

use Oro\Bundle\EntityBundle\Form\Type\EntityFieldChoiceType;
use Oro\Bundle\EntityExtendBundle\Extend\RelationType;

class UpdateFieldChoiceType extends EntityFieldChoiceType
{
    const NAME = 'update_field_choice';

    /**
     * {@inheritdoc}
     */
    protected function getChoices($entityName, $withRelations, $withVirtualFields)
    {
        $choiceFields    = [];
        $choiceRelations = [];

        foreach ($this->getEntityFields($entityName, $withRelations, $withVirtualFields) as $fieldName => $field) {
            if (!isset($field['relation_type'])) {
                // TODO: filter fields using entity config (form scope)
                $choiceFields[$fieldName] = $field['label'];
            } elseif (!in_array($field['relation_type'], RelationType::$toManyRelations)) {
                // disable mass update for *-to-many relations
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
