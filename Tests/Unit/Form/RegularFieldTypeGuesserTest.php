<?php

namespace Trustify\Bundle\MassUpdateBundle\Tests\Unit\Form;

use Doctrine\Common\Persistence\ManagerRegistry;

use Oro\Bundle\EntityBundle\Provider\EntityFieldProvider;
use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Extend\RelationType;

use Trustify\Bundle\MassUpdateBundle\Form\Guesser\RegularFieldTypeGuesser;

class RegularFieldTypeGuesserTest extends \PHPUnit_Framework_TestCase
{
    /** @var EntityFieldProvider|\PHPUnit_Framework_MockObject_MockObject */
    protected $fieldProviderMock;

    /** @var ManagerRegistry|\PHPUnit_Framework_MockObject_MockObject */
    protected $managerRegistryMock;

    /** @var ConfigProvider|\PHPUnit_Framework_MockObject_MockObject */
    protected $entityConfigMock;

    /** @var ConfigProvider|\PHPUnit_Framework_MockObject_MockObject */
    protected $formConfigMock;

    /** @var ConfigProvider|\PHPUnit_Framework_MockObject_MockObject */
    protected $extendConfigMock;

    /** @var ConfigProvider|\PHPUnit_Framework_MockObject_MockObject */
    protected $enumConfigMock;

    /** @var RegularFieldTypeGuesser */
    protected $guesser;

    public function setUp()
    {
        $this->managerRegistryMock = $this->getMock('Doctrine\Common\Persistence\ManagerRegistry');

        $this->entityConfigMock = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider')
            ->disableOriginalConstructor()
            ->getMock();
        $this->formConfigMock = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider')
            ->disableOriginalConstructor()
            ->getMock();
        $this->extendConfigMock = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider')
            ->disableOriginalConstructor()
            ->getMock();
        $this->enumConfigMock = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider')
            ->disableOriginalConstructor()
            ->getMock();
        $this->fieldProviderMock = $this->getMockBuilder('Oro\Bundle\EntityBundle\Provider\EntityFieldProvider')
            ->disableOriginalConstructor()
            ->getMock();

        $this->guesser = new RegularFieldTypeGuesser(
            $this->managerRegistryMock,
            $this->entityConfigMock,
            $this->formConfigMock,
            $this->extendConfigMock,
            $this->enumConfigMock
        );

        $this->guesser->setFieldProvider($this->fieldProviderMock);
    }

    /**
     * @return array
     */
    public function guessTypeProvider()
    {
        return [
            'no many-to one mapping' => [
                true,  // hasConfig
                true,  // isEnabled
                false, // isManyToOneExists,
                'text',
                []
            ],
            'with many-to one mapping' => [
                true,  // hasConfig
                true,  // isEnabled
                true,  // isManyToOneExists
                'entity',
                ['class' => 'Test\EntityRelated', 'label' => 'Test Label', 'required' => false, 'block' => 'general'],
            ],
        ];
    }

    /**
     * @param bool   $hasConfig
     * @param bool   $isEnabled
     * @param bool   $isManyToOneExists
     * @param string $expectedType
     * @param array  $expectedOptions
     *
     * @dataProvider guessTypeProvider
     */
    public function testGuessType($hasConfig, $isEnabled, $isManyToOneExists, $expectedType, $expectedOptions)
    {
        $className = 'Test\Entity';
        $relatedEntityName = 'Test\EntityRelated';
        $property  = 'subject';

        $this->setupExtendHasConfig(
            $className,
            $property,
            $hasConfig,
            ['is_deleted' => false, 'state' => ExtendScope::STATE_ACTIVE, 'target_entity' => $relatedEntityName]
        );
        $this->setupFormIsEnabled($className, $property, RelationType::TO_ONE, $isEnabled);

        if ($hasConfig && $isEnabled) {
            $this->fieldProviderMock->expects($this->once())
                ->method('getFields')
                ->with($className, true)
                ->willReturn([['name' => 'subject', 'related_entity_name' => $relatedEntityName]]);
        }

        if ($isManyToOneExists) {
            $this->setupEntityConfig($className, $property);

            $this->guesser->addExtendTypeMapping(RelationType::TO_ONE, 'entity');
        }

        $guess = $this->guesser->guessType($className, $property);

        $this->assertEquals(
            $expectedType,
            $guess->getType(),
            'Failed asserting type guessed correctly'
        );

        $this->assertEquals(
            $expectedOptions,
            $guess->getOptions(),
            'Failed asserting options guessed correctly'
        );
    }

    /**
     * @param string    $className
     * @param string    $property
     * @param bool|true $hasConfig
     * @param array     $extendOptions
     */
    protected function setupExtendHasConfig($className, $property, $hasConfig = true, array $extendOptions = [])
    {
        $this->extendConfigMock->expects($this->at(0))
            ->method('hasConfig')
            ->with($className, $property)
            ->willReturn($hasConfig);

        if ($hasConfig) {
            $this->extendConfigMock->expects($this->at(1))
                ->method('getConfig')
                ->with($className, $property)
                ->willReturn(
                    new Config(
                        new FieldConfigId('form', $className, $property),
                        $extendOptions
                    )
                );

            $this->extendConfigMock->expects($this->at(2))
                ->method('getConfig')
                ->with('Test\EntityRelated', null)
                ->willReturn(
                    new Config(
                        new EntityConfigId('extend', $className),
                        ['is_deleted' => false]
                    )
                );
        }
    }

    /**
     * @param string    $className
     * @param string    $property
     * @param string    $propertyType
     * @param bool|true $isEnabled
     */
    protected function setupFormIsEnabled($className, $property, $propertyType, $isEnabled = true)
    {
        $this->formConfigMock->expects($this->once())
            ->method('getConfig')
            ->with($className, $property)
            ->willReturn(
                new Config(
                    new FieldConfigId('form', $className, $property, $propertyType),
                    ['is_enabled' => $isEnabled]
                )
            );
    }

    /**
     * @param string $className
     * @param string $property
     */
    protected function setupEntityConfig($className, $property)
    {
        $this->entityConfigMock->expects($this->once())
            ->method('getConfig')
            ->with($className, $property)
            ->willReturn(
                new Config(
                    new FieldConfigId('entity', $className, $property),
                    ['label' => 'Test Label']
                )
            );
    }
}
