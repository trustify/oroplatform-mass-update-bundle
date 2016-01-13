<?php

namespace Trustify\Bundle\MassUpdateBundle\Tests\Unit\Form\Type;

use Symfony\Component\Form\FormTypeGuesserChain;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityMergeBundle\EventListener\Metadata\EntityConfigHelper;
use Oro\Bundle\FormBundle\Form\Type\OroDateTimeType;
use Oro\Bundle\FormBundle\Form\Type\OroDateType;
use Oro\Bundle\UserBundle\Entity\User;

use Trustify\Bundle\MassUpdateBundle\Form\Type\GuessFieldType;

class GuessFieldTypeTest extends TypeTestCase
{
    /** @var EntityConfigHelper|\PHPUnit_Framework_MockObject_MockObject */
    protected $entityConfigMock;

    /** @var FormTypeGuesserInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $extendTypeGuesser;

    /** @var FormTypeGuesserInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $regularTypeGuesser;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->entityConfigMock = $this->getMockBuilder(EntityConfigHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->extendTypeGuesser = $this->getMockBuilder(FormTypeGuesserInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->regularTypeGuesser = $this->getMock(FormTypeGuesserInterface::class);

        parent::setUp();
    }

    /**
     * {@inheritdoc}
     */
    protected function getExtensions()
    {
        return [
            new PreloadedExtension(
                [
                    'oro_datetime' => new OroDateTimeType(),
                    'oro_date'     => new OroDateType(),
                ],
                [],
                new FormTypeGuesserChain(
                    [
                        $this->extendTypeGuesser
                    ]
                )
            )
        ];
    }

    public function testBuildFormRegularGuesser()
    {
        $entityName   = User::class;
        $fieldName    = 'createdAt';
        $expectedType = 'oro_datetime';

        $this->regularTypeGuesser->expects($this->once())
            ->method('guessType')
            ->with($entityName, $fieldName)
            ->willReturn(new TypeGuess($expectedType, [], Guess::HIGH_CONFIDENCE));

        $type = new GuessFieldType($this->entityConfigMock, $this->regularTypeGuesser);
        $form = $this->factory->create(
            $type,
            null,
            [
                'data_class' => $entityName,
                'field_name' => $fieldName,
            ]
        );

        $view = $form->createView();

        $this->assertCount(1, $view->children, 'Failed asserting that there is only one children');
        $fieldView = $view->offsetGet($fieldName);

        $this->assertEquals(
            sprintf('guess_field_type[%s]', $fieldName),
            $fieldView->vars['full_name'],
            'Failed asserting that field name is correct'
        );

        $this->assertEquals(
            $expectedType,
            $form[$fieldName]->getConfig()->getType()->getName(),
            'Failed asserting that correct underlying type was used'
        );
    }

    /**
     * @expectedException        \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage Both "class_name" and "field_name" options must be set.
     */
    public function testBuildFormError()
    {
        $type = new GuessFieldType($this->entityConfigMock, $this->regularTypeGuesser);
        $this->factory->create($type);
    }

    public function testBuildFormExtendGuesser()
    {
        $entityName   = User::class;
        $fieldName    = 'orderPlacedAt'; // pretend that it is extend field
        $expectedType = 'oro_datetime';

        $this->entityConfigMock->expects($this->once())
            ->method('getConfig')
            ->with('extend', $entityName, $fieldName)
            ->willReturn(new Config(new FieldConfigId('extend', $entityName, $fieldName), ['is_extend' => true]));

        $this->extendTypeGuesser->expects($this->once())
            ->method('guessType')
            ->with($entityName, $fieldName)
            ->willReturn(new TypeGuess($expectedType, [], Guess::HIGH_CONFIDENCE));

        $type = new GuessFieldType($this->entityConfigMock, $this->regularTypeGuesser);
        $form = $this->factory->create(
            $type,
            null,
            [
                'data_class' => $entityName,
                'field_name' => $fieldName,
            ]
        );

        $view = $form->createView();

        $this->assertCount(1, $view->children, 'Failed asserting that there is only one children');
        $fieldView = $view->offsetGet($fieldName);

        $this->assertEquals(
            sprintf('guess_field_type[%s]', $fieldName),
            $fieldView->vars['full_name'],
            'Failed asserting that field name is correct'
        );

        $this->assertEquals(
            $expectedType,
            $form[$fieldName]->getConfig()->getType()->getName(),
            'Failed asserting that correct underlying type was used'
        );
    }

    /**
     * @return array
     */
    public function mappingTestProvider()
    {
        return [
            'all mapping'      => [
                ['all' => ['someCustomField' => ['type' => 'oro_datetime', 'options' => []]]]
            ],
            'specific mapping' => [
                [
                    User::class => [
                        'someCustomField' => [
                            'type'    => 'oro_datetime',
                            'options' => []
                        ]
                    ]
                ]
            ],
        ];
    }

    /**
     * @dataProvider mappingTestProvider
     *
     * @param array $mapping
     */
    public function testBuildFormMappingUsed(array $mapping)
    {
        $entityName   = User::class;
        $fieldName    = 'someCustomField';
        $expectedType = 'oro_datetime';

        $this->regularTypeGuesser->expects($this->never())->method('guessType');
        $this->extendTypeGuesser->expects($this->never())->method('guessType');

        $type = new GuessFieldType($this->entityConfigMock, $this->regularTypeGuesser, $mapping);

        $form = $this->factory->create(
            $type,
            null,
            [
                'data_class' => $entityName,
                'field_name' => $fieldName,
            ]
        );

        $view = $form->createView();

        $this->assertCount(1, $view->children, 'Failed asserting that there is only one children');
        $fieldView = $view->offsetGet($fieldName);

        $this->assertEquals(
            sprintf('guess_field_type[%s]', $fieldName),
            $fieldView->vars['full_name'],
            'Failed asserting that field name is correct'
        );

        $this->assertEquals(
            $expectedType,
            $form[$fieldName]->getConfig()->getType()->getName(),
            'Failed asserting that correct underlying type was used'
        );
    }
}
