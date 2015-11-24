<?php

namespace Trustify\Bundle\MassUpdateBundle\Form\Guesser;

use Oro\Bundle\EntityBundle\Provider\EntityFieldProvider;
use Oro\Bundle\EntityConfigBundle\Config\ConfigInterface;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Extend\RelationType;
use Oro\Bundle\EntityExtendBundle\Form\Guesser\ExtendFieldTypeGuesser;

class RegularFieldTypeGuesser extends ExtendFieldTypeGuesser
{
    /** @var EntityFieldProvider */
    protected $fieldProvider;

    /** @var array */
    protected $fieldList = [];

    /**
     * @param EntityFieldProvider $fieldProvider
     */
    public function setFieldProvider(EntityFieldProvider $fieldProvider)
    {
        $this->fieldProvider = $fieldProvider;
    }

    /**
     * {@inheritdoc}
     */
    protected function isApplicableField(ConfigInterface $extendConfig)
    {
        return
            !$extendConfig->is('is_deleted')
            && !$extendConfig->is('state', ExtendScope::STATE_NEW)
            && !in_array($extendConfig->getId()->getFieldType(), [RelationType::TO_MANY])
            && (
                !$extendConfig->has('target_entity')
                || !$this->extendConfigProvider->getConfig($extendConfig->get('target_entity'))->is('is_deleted')
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function getOptions(ConfigInterface $extendConfig, FieldConfigId $fieldConfigId)
    {
        $options = parent::getOptions($extendConfig, $fieldConfigId);

        $className = $fieldConfigId->getClassName();
        $fieldName = $fieldConfigId->getFieldName();

        switch ($fieldConfigId->getFieldType()) {
            case RelationType::TO_ONE:
                if (empty($this->fieldList) && $this->fieldProvider) {
                    $fieldList = $this->fieldProvider->getFields($className, true);

                    foreach ($fieldList as $entityField) {
                        $this->fieldList[$entityField['name']] = $entityField;
                    }
                }

                $options['class'] = $this->fieldList[$fieldName]['related_entity_name'];

                break;
        }

        return $options;
    }
}
