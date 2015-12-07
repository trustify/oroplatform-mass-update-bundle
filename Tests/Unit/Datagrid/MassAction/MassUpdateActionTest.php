<?php

namespace Trustify\Bundle\MassUpdateBundle\Tests\Unit\Datagrid\MassAction;

use Psr\Log\LoggerInterface;

use Symfony\Component\Translation\TranslatorInterface;

use Oro\Bundle\DataGridBundle\Extension\Action\ActionConfiguration;
use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\DataGridBundle\Extension\MassAction\Actions\MassActionInterface;
use Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionHandlerArgs;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProviderInterface;
use Oro\Bundle\SecurityBundle\SecurityFacade;

use Trustify\Bundle\MassUpdateBundle\Datagrid\MassAction\ActionRepository;
use Trustify\Bundle\MassUpdateBundle\Datagrid\MassAction\MassUpdateActionHandler;

class MassUpdateActionTest extends \PHPUnit_Framework_TestCase
{
    /** @var MassUpdateActionHandler */
    protected $handler;

    /** @var ConfigProviderInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $configMock;

    /** @var TranslatorInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $transMock;

    /** @var SecurityFacade|\PHPUnit_Framework_MockObject_MockObject */
    protected $securityMock;

    /** @var ActionRepository|\PHPUnit_Framework_MockObject_MockObject */
    protected $actionRepoMock;

    /** @var LoggerInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $loggerMock;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->configMock = $this->getMock(ConfigProviderInterface::class);
        $this->transMock = $this->getMock(TranslatorInterface::class);
        $this->securityMock = $this->getMockBuilder(SecurityFacade::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->actionRepoMock = $this->getMockBuilder(ActionRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->handler = new MassUpdateActionHandler(
            $this->configMock,
            $this->transMock,
            $this->securityMock,
            $this->actionRepoMock
        );

        $this->loggerMock = $this->getMock('Psr\Log\LoggerInterface');
        $this->handler->setLogger($this->loggerMock);
    }

    public function testHandleSuccess()
    {
        $massActionMock = $this->getMock(MassActionInterface::class);
        $datagridMock   = $this->getMock('Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface');
        $iteratorMock   = $this->getMock('Oro\Bundle\DataGridBundle\Datasource\Orm\IterableResultInterface');

        $entityName    = 'Test\EntityName';
        $hasConfig     = true;
        $isEnabled     = true;
        $isGranted     = true;
        $entitiesCount = 1;
        $data          = [];

        $this->actionRepoMock->expects($this->once())
            ->method('getEntityName')
            ->with($datagridMock)
            ->will($this->returnValue($entityName));

        $this->configMock->expects($this->once())
            ->method('hasConfig')
            ->with($entityName)
            ->will($this->returnValue($hasConfig));

        $this->configMock->expects($this->once())
            ->method('getConfig')
            ->with($entityName)
            ->will(
                $this->returnValue(
                    new Config(new EntityConfigId('extend', $entityName), ['update_mass_action_enabled' => $isEnabled])
                )
            );

        $this->securityMock->expects($this->once())
            ->method('isGranted')
            ->with('EDIT', 'entity:' . $entityName)
            ->will($this->returnValue($isGranted));

        $this->actionRepoMock->expects($this->once())
            ->method('batchUpdate')
            ->with($massActionMock, $iteratorMock, $data)
            ->will($this->returnValue($entitiesCount));

        $this->transMock->expects($this->once())
            ->method('trans')
            ->will($this->returnValue(uniqid()));

        $options = ActionConfiguration::create(['success_message' => '', 'error_message' => '']);
        $massActionMock->expects($this->any())
            ->method('getOptions')
            ->will($this->returnValue($options));

        $actionResponse = $this->handler->handle(
            new MassActionHandlerArgs($massActionMock, $datagridMock, $iteratorMock, $data)
        );

        $this->assertEquals($entitiesCount > 0, $actionResponse->isSuccessful(), 'Fail to perform action');
        $this->assertEquals(
            $entityName,
            $options->offsetGet('entityName'),
            'Failed asserting that entity name was added to options'
        );
    }

    public function testHandleNoEntityName()
    {
        $massActionMock = $this->getMock(MassActionInterface::class);
        $datagridMock   = $this->getMock('Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface');
        $iteratorMock   = $this->getMock('Oro\Bundle\DataGridBundle\Datasource\Orm\IterableResultInterface');

        $entityName    = null;
        $data          = [];

        $this->actionRepoMock->expects($this->once())
            ->method('getEntityName')
            ->with($datagridMock)
            ->will($this->returnValue($entityName));

        $this->configMock->expects($this->never())->method('hasConfig');
        $this->configMock->expects($this->never())->method('getConfig');

        $this->transMock->expects($this->once())
            ->method('trans')
            ->will($this->returnValue(uniqid()));

        $options = ActionConfiguration::create(['success_message' => '', 'error_message' => '']);
        $massActionMock->expects($this->any())
            ->method('getOptions')
            ->will($this->returnValue($options));

        $this->transMock->expects($this->once())
            ->method('trans')
            ->with('', ['%error%' => 'Action not configured or not allowed']);

        $actionResponse = $this->handler->handle(
            new MassActionHandlerArgs($massActionMock, $datagridMock, $iteratorMock, $data)
        );

        $this->assertFalse($actionResponse->isSuccessful());
    }

    public function testHandleUpdateNotAllowed()
    {
        $massActionMock = $this->getMock(MassActionInterface::class);
        $datagridMock   = $this->getMock('Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface');
        $iteratorMock   = $this->getMock('Oro\Bundle\DataGridBundle\Datasource\Orm\IterableResultInterface');

        $entityName    = 'Test\EntityName';
        $hasConfig     = true;
        $isEnabled     = true;
        $isGranted     = false;
        $data = [];

        $this->actionRepoMock->expects($this->once())
            ->method('getEntityName')
            ->with($datagridMock)
            ->will($this->returnValue($entityName));

        $this->configMock->expects($this->once())
            ->method('hasConfig')
            ->with($entityName)
            ->will($this->returnValue($hasConfig));

        $this->configMock->expects($this->once())
            ->method('getConfig')
            ->with($entityName)
            ->will(
                $this->returnValue(
                    new Config(new EntityConfigId('extend', $entityName), ['update_mass_action_enabled' => $isEnabled])
                )
            );

        $this->securityMock->expects($this->once())
            ->method('isGranted')
            ->with('EDIT', 'entity:' . $entityName)
            ->will($this->returnValue($isGranted));

        $this->actionRepoMock->expects($this->never())->method('batchUpdate');

        $this->transMock->expects($this->once())
            ->method('trans')
            ->will($this->returnValue(uniqid()));

        $options = ActionConfiguration::create(['success_message' => '', 'error_message' => '']);
        $massActionMock->expects($this->any())
            ->method('getOptions')
            ->will($this->returnValue($options));

        $this->transMock->expects($this->once())
            ->method('trans')
            ->with('', ['%error%' => 'Action not configured or not allowed']);

        $this->loggerMock->expects($this->once())
            ->method('debug');

        $actionResponse = $this->handler->handle(
            new MassActionHandlerArgs($massActionMock, $datagridMock, $iteratorMock, $data)
        );

        $this->assertFalse($actionResponse->isSuccessful());
    }
}
