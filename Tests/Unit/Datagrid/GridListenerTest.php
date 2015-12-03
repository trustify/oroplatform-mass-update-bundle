<?php

namespace Trustify\Bundle\MassUpdateBundle\Tests\Unit\Datagrid;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datagrid\Datagrid;
use Oro\Bundle\DataGridBundle\Datagrid\ParameterBag;
use Oro\Bundle\DataGridBundle\Event\BuildBefore;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityBundle\ORM\EntityClassResolver;
use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProviderInterface;

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

    /** @var ConfigProviderInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $configProviderMock;

    protected function setUp()
    {
        $this->classResolverMock = $this->getMockBuilder(EntityClassResolver::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->doctrineHelperMock = $this->getMockBuilder(DoctrineHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->configProviderMock = $this->getMock(ConfigProviderInterface::class);

        $this->listener = new GridListener(
            $this->classResolverMock,
            $this->doctrineHelperMock,
            $this->configProviderMock
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
                true,   // hasConfig
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
                true,   // hasConfig
                true,   // isActionEnabled
            ],
            'not an entity' => [
                'Some\Class\NotAnEntity',
                false,  // isEntity
                false,  // isException
                false,  // isAlreadyConfigured
                true,   // hasConfig
                true,   // isActionEnabled
            ],
            'entity is ok, but no entity config' => [
                'Test\TestEntity',
                true,   // isEntity
                false,  // isException
                false,  // isAlreadyConfigured
                false,  // hasConfig
                false,  // isActionEnabled
            ],
            'entity is ok, config exists but not action enabled' => [
                'Test\TestEntity',
                true,   // isEntity
                false,  // isException
                false,  // isAlreadyConfigured
                true,   // hasConfig
                false,  // isActionEnabled
            ],
            'entity is ok, action enabled, but already configured elsewhere' => [
                'Test\TestEntity',
                true,   // isEntity
                false,  // isException
                true,   // isAlreadyConfigured
                true,   // hasConfig
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
     * @param bool       $hasConfig
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
        $hasConfig,
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

            $this->configProviderMock->expects($this->once())
                ->method('hasConfig')
                ->with($entityName)
                ->will($this->returnValue($hasConfig));

            if ($hasConfig) {
                $this->configProviderMock->expects($this->once())
                    ->method('getConfig')
                    ->with($entityName)
                    ->will(
                        $this->returnValue(
                            new Config(
                                new EntityConfigId('entity', $entityName),
                                ['update_mass_action_enabled' => $isActionEnabled]
                            )
                        )
                    );
            }
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
