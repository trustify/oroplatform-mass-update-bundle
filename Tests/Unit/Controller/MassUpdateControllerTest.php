<?php

namespace Trustify\Bundle\MassUpdateBundle\Tests\Unit\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

use Oro\Bundle\SecurityBundle\SecurityFacade;

use Trustify\Bundle\MassUpdateBundle\Controller\MassUpdateController;

class MassUpdateControllerTest extends \PHPUnit_Framework_TestCase
{
    /** @var MassUpdateController */
    protected $controller;

    /** @var ContainerInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $containerMock;

    /** @var SecurityFacade|\PHPUnit_Framework_MockObject_MockObject */
    protected $securityMock;

    /** @var FormFactoryInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $formFactoryMock;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->containerMock = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');

        $this->controller = new MassUpdateController();
        $this->controller->setContainer($this->containerMock);

        $this->securityMock = $this->getMockBuilder('Oro\Bundle\SecurityBundle\SecurityFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $this->formFactoryMock = $this->getMock('Symfony\Component\Form\FormFactoryInterface');
    }

    /**
     * @return array
     */
    public function dataProvider()
    {
        return [
            'no field form'                  => [false, true, false, false],
            'field form'                     => [true, true, false, false],
            'entity update forbidden'        => [true, false, false, false],
            'field acl enabled'              => [true, true, true, true],
            'field acl enabled, not granted' => [true, true, true, false],
        ];
    }

    /**
     * @param bool $isFieldForm
     * @param bool $isEntityGranted
     * @param bool $isFieldAclEnabled
     * @param bool $isFieldGranted
     *
     * @dataProvider dataProvider
     */
    public function testMassUpdateAction($isFieldForm, $isEntityGranted, $isFieldAclEnabled, $isFieldGranted)
    {
        $this->configureSecurityMock($isEntityGranted, $isFieldAclEnabled, $isFieldGranted);

        /** @var Request|\PHPUnit_Framework_MockObject_MockObject $request */
        $request = $this->getMock(Request::class);
        $request->expects($this->at(0))
            ->method('get')
            ->with('entityName')
            ->willReturn('Test_Entity');

        $request->expects($this->at(1))
            ->method('get')
            ->with('selectedFormField')
            ->willReturn($isFieldForm ? 'someField' : null);

        if ($isEntityGranted && !($isFieldAclEnabled && !$isFieldGranted)) {
            $this->configureFormMock($isFieldForm);
        }

        if ($isEntityGranted && $isFieldForm) {
            $this->configureSettingsMock($isFieldAclEnabled);
        }

        if (!$isEntityGranted || ($isFieldAclEnabled && !$isFieldGranted)) {
            $this->setExpectedException('Symfony\Component\Security\Core\Exception\AccessDeniedException');
        }

        $this->controller->massUpdateAction($request);
    }

    /**
     * @param bool|false $isFieldAclEnabled
     */
    protected function configureSettingsMock($isFieldAclEnabled = false)
    {
        $configMock = $this->getMockBuilder('Oro\Bundle\ConfigBundle\Config\ConfigManager')
            ->disableOriginalConstructor()
            ->getMock();
        $configMock->expects($this->once())
            ->method('get')
            ->with('trustify_mass_update.field_acl_enabled')
            ->willReturn($isFieldAclEnabled);

        $this->containerMock->expects($this->at(2))
            ->method('get')
            ->with('oro_config.manager')
            ->willReturn($configMock);
    }

    /**
     * @param bool $isGranted
     * @param bool $isFieldAclEnabled
     * @param bool $isFieldGranted
     */
    protected function configureSecurityMock($isGranted, $isFieldAclEnabled, $isFieldGranted)
    {
        $this->containerMock->expects($this->at(0))
            ->method('get')
            ->with('oro_security.security_facade')
            ->willReturn($this->securityMock);

        $this->securityMock->expects($this->at(0))
            ->method('isGranted')
            ->with('EDIT', 'entity:Test\Entity')
            ->willReturn($isGranted);

        if ($isFieldAclEnabled) {
            $this->containerMock->expects($this->at(3))
                ->method('get')
                ->with('oro_security.security_facade')
                ->willReturn($this->securityMock);

            $this->securityMock->expects($this->at(1))
                ->method('isGranted')
                ->with('EDIT', $this->isInstanceOf('Symfony\Component\Security\Acl\Voter\FieldVote'))
                ->willReturn($isFieldGranted);

            $doctrineHelperMock = $this->getMockBuilder('Oro\Bundle\EntityBundle\ORM\DoctrineHelper')
                ->disableOriginalConstructor()
                ->getMock();
            $doctrineHelperMock
                ->expects($this->once())
                ->method('createEntityInstance')
                ->with('Test\Entity')
                ->willReturn(new \stdClass());

            $this->containerMock->expects($this->at(4))
                ->method('get')
                ->with('oro_entity.doctrine_helper')
                ->willReturn($doctrineHelperMock);
        }
    }

    /**
     * @param bool $isFieldForm
     */
    protected function configureFormMock($isFieldForm)
    {
        $this->containerMock->expects($this->at(1))
            ->method('get')
            ->with('form.factory')
            ->willReturn($this->formFactoryMock);

        $formMock = $this->getMock('Symfony\Component\Form\FormInterface');
        $invocationMocker = $this->formFactoryMock->expects($this->once())
            ->method('createNamed')
            ->willReturn($formMock);

        if ($isFieldForm) {
            $invocationMocker->with(
                '',
                'guess_field_type',
                null,
                [
                    'data_class'              => 'Test\Entity',
                    'field_name'              => 'someField',
                    'dynamic_fields_disabled' => true,
                    'ownership_disabled'      => true
                ]
            );
        } else {
            $invocationMocker->with(
                'mass_edit_field',
                'update_field_choice',
                null,
                [
                    'entity'         => 'Test\Entity',
                    'with_relations' => true
                ]
            );
        }

        $formMock->expects($this->once())
            ->method('createView')
            ->willReturn($this->getMock('Symfony\Component\Form\FormView'));
    }
}
