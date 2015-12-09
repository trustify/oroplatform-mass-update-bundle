<?php

namespace Trustify\Bundle\MassUpdateBundle\Tests\Unit\Form\Type;

use Genemu\Bundle\FormBundle\Form\JQuery\Type\Select2Type;

use Symfony\Component\Form\Extension\Core\View\ChoiceView;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Translation\TranslatorInterface;

use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProviderInterface;
use Oro\Bundle\EntityBundle\Provider\EntityFieldProvider;
use Oro\Bundle\EntityBundle\Provider\EntityProvider;

use Trustify\Bundle\MassUpdateBundle\Form\Type\UpdateFieldChoiceType;

class UpdateFieldChoiceTypeTest extends TypeTestCase
{
    /** @var EntityProvider|\PHPUnit_Framework_MockObject_MockObject */
    protected $entityProviderMock;

    /** @var EntityFieldProvider|\PHPUnit_Framework_MockObject_MockObject */
    protected $entityFieldMock;

    /** @var TranslatorInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $translatorMock;

    /** @var ConfigProviderInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $formConfigMock;

    /** @var DoctrineHelper|\PHPUnit_Framework_MockObject_MockObject */
    protected $doctrineHelperMock;

    /** @var UpdateFieldChoiceType */
    protected $type;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->entityProviderMock = $this->getMockBuilder(EntityProvider::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->entityFieldMock = $this->getMockBuilder(EntityFieldProvider::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->translatorMock = $this->getMock(TranslatorInterface::class);
        $this->formConfigMock = $this->getMock(ConfigProviderInterface::class);
        $this->doctrineHelperMock = $this->getMockBuilder(DoctrineHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->type = new UpdateFieldChoiceType(
            $this->entityProviderMock,
            $this->entityFieldMock,
            $this->translatorMock
        );
        $this->type->setFormConfigProvider($this->formConfigMock);
        $this->type->setDoctrineHelper($this->doctrineHelperMock);

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
                    'genemu_jqueryselect2_choice' => new Select2Type('choice'),
                    'genemu_jqueryselect2_hidden' => new Select2Type('hidden')
                ],
                []
            )
        ];
    }

    /**
     * @return array
     */
    public function testDataProvider()
    {
        return [
            'with relations'    => [true],
            'without relations' => [false],
        ];
    }

    /**
     * @param bool $withRelations
     *
     * @dataProvider testDataProvider
     */
    public function testBuildFormRegularGuesser($withRelations)
    {
        $entityName = 'Test\Entity';

        $this->doctrineHelperMock->expects($this->once())
            ->method('getEntityIdentifierFieldNames')
            ->with($entityName)
            ->willReturn(['id']);

        $fields = [
            ['name' => 'oneField', 'type' => 'string', 'label' => 'One field'],
            ['name' => 'anotherField', 'type' => 'string', 'label' => 'Another field'],
        ];

        if ($withRelations) {
            $fields[] = ['name' => 'relField', 'relation_type' => 'ref-one', 'label' => 'Many to One field'];
        }

        $this->entityFieldMock->expects($this->once())
            ->method('getFields')
            ->willReturn($fields);

        $this->formConfigMock->expects($this->at(0))
            ->method('getConfig')
            ->with($entityName, 'oneField')
            ->willReturn(new Config(new FieldConfigId('form', $entityName, 'someField'), ['is_enabled' => false]));

        $this->formConfigMock->expects($this->at(1))
            ->method('getConfig')
            ->with($entityName, 'anotherField')
            ->willReturn(new Config(new FieldConfigId('form', $entityName, 'anotherField'), ['is_enabled' => true]));

        if ($withRelations) {
            $this->formConfigMock->expects($this->at(2))
                ->method('getConfig')
                ->with($entityName, 'relField')
                ->willReturn(new Config(new FieldConfigId('form', $entityName, 'relField'), ['is_enabled' => true]));

            $this->translatorMock->expects($this->at(0))
                ->method('trans')
                ->with('oro.entity.form.entity_fields')
                ->willReturn('Fields');

            $this->translatorMock->expects($this->at(1))
                ->method('trans')
                ->with('oro.entity.form.entity_related')
                ->willReturn('Relations');
        }

        $form = $this->factory->create(
            $this->type,
            null,
            ['entity' => $entityName, 'with_relations' => $withRelations]
        );
        $view = $form->createView();

        $this->assertEquals(
            'update_field_choice',
            $view->vars['full_name'],
            'Failed asserting that field name is correct'
        );

        $this->assertNotEmpty($view->vars['configs']['component']);
        $this->assertEquals('entity-field-choice', $view->vars['configs']['component']);

        $this->assertEquals(
            'update_field_choice',
            $form->getConfig()->getType()->getName(),
            'Failed asserting that correct underlying type was used'
        );

        if ($withRelations) {
            $this->assertCount(2, $view->vars['choices'], 'Failed asserting that choices are grouped');
        } else {
            $this->assertCount(1, $view->vars['choices'], 'Failed asserting that choices exists');

            /** @var ChoiceView $choice */
            $choice = reset($view->vars['choices']);
            $this->assertEquals('Another field', $choice->label);
        }
    }
}
