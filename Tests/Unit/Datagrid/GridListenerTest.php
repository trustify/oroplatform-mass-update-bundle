<?php

namespace Trustify\Bundle\MassUpdateBundle\Tests\Unit\Datagrid;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datagrid\Datagrid;
use Oro\Bundle\DataGridBundle\Datagrid\ParameterBag;
use Oro\Bundle\DataGridBundle\Event\BuildBefore;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityBundle\ORM\EntityClassResolver;

use Trustify\Bundle\MassUpdateBundle\Datagrid\GridListener;
use Trustify\Bundle\MassUpdateBundle\Datagrid\MassAction\MassUpdateActionHandler;

class GridListenerTest extends \PHPUnit_Framework_TestCase
{
    /** @var GridListener */
    protected $listener;

    /** @var EntityClassResolver|\PHPUnit_Framework_MockObject_MockObject */
    protected $classResolverMock;

    /** @var DoctrineHelper|\PHPUnit_Framework_MockObject_MockObject */
    protected $doctrineHelperMock;

    /** @var MassUpdateActionHandler|\PHPUnit_Framework_MockObject_MockObject */
    protected $handlerMock;

    protected function setUp()
    {
        $this->classResolverMock = $this->getMockBuilder(EntityClassResolver::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->doctrineHelperMock = $this->getMockBuilder(DoctrineHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->handlerMock = $this->getMockBuilder(MassUpdateActionHandler::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->listener = new GridListener(
            $this->classResolverMock,
            $this->doctrineHelperMock,
            $this->handlerMock
        );
    }

    /**
     * @return array
     */
    public function onBuildBeforeProvider()
    {
        return [
            'all enabled' => [
                'Test\TestEntity',
                true,   // isEntity
                false,  // isException
                false,  // isAlreadyConfigured
                true,   // isActionEnabled
                // expected
                [
                    'type'                => 'window',
                    'frontend_type'       => 'update-mass',
                    'route'               => 'oro_datagrid_mass_action',
                    'dialogWindowOptions' => [
                        'route'            => 'trustify_mass_update',
                        'route_parameters' => ['entityName' => 'Test_TestEntity'],
                    ],
                    'data_identifier'     => 'e.id',
                    'handler'             => MassUpdateActionHandler::SERVICE_ID,
                    'label'               => 'trustify.mass_update.dialog.title',
                    'success_message'     => 'trustify.mass_update.success_message',
                    'error_message'       => 'trustify.mass_update.error_message',
                ]
            ],
            'invalid class, exception' => [
                'NonExistendClass',
                false,  // isEntity
                true,   // isException
                false,  // isAlreadyConfigured
                true,   // isActionEnabled
            ],
            'not an entity' => [
                'Some\Class\NotAnEntity',
                false,  // isEntity
                false,  // isException
                false,  // isAlreadyConfigured
                true,   // isActionEnabled
            ],
            'entity is ok, but no entity config' => [
                'Test\TestEntity',
                true,   // isEntity
                false,  // isException
                false,  // isAlreadyConfigured
                false,  // isActionEnabled
            ],
            'entity is ok, config exists but not action enabled' => [
                'Test\TestEntity',
                true,   // isEntity
                false,  // isException
                false,  // isAlreadyConfigured
                false,  // isActionEnabled
            ],
            'entity is ok, action enabled, but already configured elsewhere' => [
                'Test\TestEntity',
                true,   // isEntity
                false,  // isException
                true,   // isAlreadyConfigured
                true,   // isActionEnabled
                ['action settings']
            ],
        ];
    }

    /**
     * @param string     $entityName
     * @param bool       $isEntity
     * @param bool       $isException
     * @param bool       $isAlreadyConfigured
     * @param bool       $isActionEnabled
     * @param array|null $expected
     *
     * @dataProvider onBuildBeforeProvider
     */
    public function testOnBuildBefore(
        $entityName,
        $isEntity,
        $isException,
        $isAlreadyConfigured,
        $isActionEnabled,
        $expected = null
    ) {
        $datagrid = $this->getDatagrid($entityName, $isAlreadyConfigured);

        // prepare mocks
        if ($isException) {
            $this->classResolverMock->expects($this->once())
                ->method('isEntity')
                ->with($entityName)
                ->will($this->throwException(new \ReflectionException("Not valid class")));
        } else {
            $this->classResolverMock->expects($this->once())
                ->method('isEntity')
                ->with($entityName)
                ->will($this->returnValue($isEntity));

            if ($isEntity && !$isAlreadyConfigured && $isActionEnabled) {
                $this->doctrineHelperMock->expects($this->once())
                    ->method('getSingleEntityIdentifierFieldName')
                    ->will($this->returnValue('id'));
            }

            $isEmptyAndEnabled = !$isAlreadyConfigured && $isEntity;
            $this->handlerMock->expects($isEmptyAndEnabled ? $this->once() : $this->never())
                ->method('isMassActionEnabled')
                ->with($entityName)
                ->will($this->returnValue($isActionEnabled));
        }

        $event = new BuildBefore($datagrid, $datagrid->getConfig());
        $this->listener->onBuildBefore($event);

        $this->assertEquals(
            $expected,
            $event->getConfig()->offsetGetByPath(
                sprintf(GridListener::ACTION_CONFIGURATION_KEY, MassUpdateActionHandler::ACTION_NAME)
            ),
            'Failed asserting that mass action config added by listener'
        );
    }

    /**
     * @param string $entityName
     * @param bool   $isAlreadyConfigured
     *
     * @return Datagrid
     */
    protected function getDatagrid($entityName, $isAlreadyConfigured)
    {
        $config = DatagridConfiguration::create(
            [
                'source' => ['query' => ['from' => [['table' => $entityName]]]],
            ]
        );

        if ($isAlreadyConfigured) {
            $config['mass_actions'] = ['mass_update' => ['action settings']];
        }

        return new Datagrid('test', $config, new ParameterBag());
    }
}
