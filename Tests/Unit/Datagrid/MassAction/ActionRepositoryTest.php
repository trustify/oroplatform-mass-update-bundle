<?php

namespace Trustify\Bundle\MassUpdateBundle\Tests\Unit\Datagrid\MassAction;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;

use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\DataGridBundle\Datasource\Orm\IterableResultInterface;
use Oro\Bundle\DataGridBundle\Datasource\ResultRecord;
use Oro\Bundle\DataGridBundle\Extension\Action\ActionConfiguration;
use Oro\Bundle\DataGridBundle\Extension\MassAction\Actions\MassActionInterface;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;

use Trustify\Bundle\MassUpdateBundle\Datagrid\MassAction\ActionRepository;

class ActionRepositoryTest extends \PHPUnit_Framework_TestCase
{
    /** @var ActionRepository */
    protected $actionRepo;

    /** @var DoctrineHelper|\PHPUnit_Framework_MockObject_MockObject */
    protected $doctrineHelperMock;

    public function setUp()
    {
        $this->doctrineHelperMock = $this->getMockBuilder('Oro\Bundle\EntityBundle\ORM\DoctrineHelper')
            ->disableOriginalConstructor()
            ->getMock();

        $this->actionRepo = new ActionRepository($this->doctrineHelperMock);
    }

    public function testBatchUpdateSuccess()
    {
        /** @var MassActionInterface|\PHPUnit_Framework_MockObject_MockObject $massActionMock */
        $massActionMock = $this->getMock(MassActionInterface::class);

        $entityName = 'Test\Entity';
        $options = ActionConfiguration::create(['entityName' => $entityName]);
        $massActionMock->expects($this->any())
            ->method('getOptions')
            ->will($this->returnValue($options));

        /** @var IterableResultInterface|\PHPUnit_Framework_MockObject_MockObject $massActionMock */
        $iteratorMock   = $this->getIteratedResultMock();

        $data = ['mass_edit_field' => 'name', 'name' => 'something'];

        $this->doctrineHelperMock->expects($this->once())
            ->method('getSingleEntityIdentifierFieldName')
            ->with($entityName)
            ->will($this->returnValue('id'));

        $this->doctrineHelperMock->expects($this->once())
            ->method('getEntityManager')
            ->with($entityName)
            ->will($this->returnValue($this->getEntityManagerMock(true, 2)));

        $entitiesCount = $this->actionRepo->batchUpdate($massActionMock, $iteratorMock, $data);

        $this->assertEquals(2, $entitiesCount, 'Failed asserting that entities count match');
    }

    /**
     * @expectedException \RuntimeException
     *
     * @throws \Exception
     */
    public function testBatchUpdateException()
    {
        /** @var MassActionInterface|\PHPUnit_Framework_MockObject_MockObject $massActionMock */
        $massActionMock = $this->getMock(MassActionInterface::class);

        $entityName = 'Test\Entity';
        $options = ActionConfiguration::create(['entityName' => $entityName]);
        $massActionMock->expects($this->any())
            ->method('getOptions')
            ->will($this->returnValue($options));

        /** @var IterableResultInterface|\PHPUnit_Framework_MockObject_MockObject $massActionMock */
        $iteratorMock   = $this->getIteratedResultMock();

        $data = ['mass_edit_field' => 'name', 'name' => 'something'];

        $this->doctrineHelperMock->expects($this->once())
            ->method('getSingleEntityIdentifierFieldName')
            ->with($entityName)
            ->will($this->returnValue('id'));

        $this->doctrineHelperMock->expects($this->once())
            ->method('getEntityManager')
            ->with($entityName)
            ->will($this->returnValue($this->getEntityManagerMock(false)));

        $this->actionRepo->batchUpdate($massActionMock, $iteratorMock, $data);
    }

    /**
     * @expectedException        \RuntimeException
     * @expectedExceptionMessage Field name was not specified with option 'mass_edit_field'
     *
     * @throws \Exception
     */
    public function testBatchUpdateNoFieldName()
    {
        /** @var MassActionInterface|\PHPUnit_Framework_MockObject_MockObject $massActionMock */
        $massActionMock = $this->getMock(MassActionInterface::class);

        $entityName = 'Test\Entity';
        $options = ActionConfiguration::create(['entityName' => $entityName]);
        $massActionMock->expects($this->any())
            ->method('getOptions')
            ->will($this->returnValue($options));

        /** @var IterableResultInterface|\PHPUnit_Framework_MockObject_MockObject $massActionMock */
        $iteratorMock   = $this->getMock('Oro\Bundle\DataGridBundle\Datasource\Orm\IterableResultInterface');

        $data = [];
        $this->actionRepo->batchUpdate($massActionMock, $iteratorMock, $data);
    }

    public function testGetEntityName()
    {
        /** @var DatagridInterface|\PHPUnit_Framework_MockObject_MockObject $datagridMock */
        $datagridMock = $this->getMockBuilder('Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $datagridMock->expects($this->once())
            ->method('getDatasource')
            ->will($this->returnValue(null));

        $resultEntityName = $this->actionRepo->getEntityName($datagridMock);
        $this->assertNull($resultEntityName, 'Failed asserting that datasource configured');

        // assert datasource configured
        $datagridMock = $this->getMockBuilder('Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $datasourceMock = $this->getMockBuilder('Oro\Bundle\DataGridBundle\Datasource\Orm\OrmDatasource')
            ->disableOriginalConstructor()
            ->getMock();
        $datagridMock->expects($this->once())
            ->method('getDatasource')
            ->will($this->returnValue($datasourceMock));

        $qbMock = $this->getMockBuilder('Doctrine\ORM\QueryBuilder')
            ->disableOriginalConstructor()
            ->getMock();
        $datasourceMock->expects($this->once())
            ->method('getQueryBuilder')
            ->willReturn($qbMock);

        $qbMock->expects($this->once())
            ->method('getRootEntities')
            ->willReturn(['Test\Entity', 'Test\AnotherEntity']);

        $resultEntityName = $this->actionRepo->getEntityName($datagridMock);
        $this->assertEquals('Test\Entity', $resultEntityName, 'Failed asserting that entity name was found');
    }

    /**
     * @param bool|true $isSuccess
     * @param int       $entitiesCount
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getEntityManagerMock($isSuccess = true, $entitiesCount = 0)
    {
        /** @var EntityManagerInterface|\PHPUnit_Framework_MockObject_MockObject $entityManagerMock */
        $entityManagerMock = $this->getMock('Doctrine\ORM\EntityManagerInterface');

        $entityManagerMock->expects($this->once())->method('beginTransaction');

        $entityManagerMock->expects($this->once())
            ->method('createQueryBuilder')
            ->will($this->returnValue(new QueryBuilder($entityManagerMock)));

        $queryMock = $this->getMock(
            'Doctrine\ORM\AbstractQuery',
            ['execute', 'getSQL', '_doExecute', 'setFirstResult', 'setMaxResults'],
            [],
            '',
            false
        );
        $queryMock->expects($this->once())
            ->method('setFirstResult')
            ->willReturn($queryMock);
        $queryMock->expects($this->once())
            ->method('setMaxResults')
            ->willReturn($queryMock);
        $queryMock->expects($this->once())
            ->method('execute')
            ->will($this->returnValue($entitiesCount));

        $entityManagerMock->expects($this->once())
            ->method('createQuery')
            ->will($this->returnValue($queryMock));

        $entityManagerMock->expects($this->once())
            ->method('getExpressionBuilder')
            ->will($this->returnValue(new Query\Expr()));

        if ($isSuccess) {
            // configure connection mock
            $connMock = $this->getMockBuilder('Doctrine\DBAL\Connection')
                ->disableOriginalConstructor()
                ->getMock();
            $connMock->expects($this->once())
                ->method('getTransactionNestingLevel')
                ->will($this->returnValue(1));
            $entityManagerMock->expects($this->once())
                ->method('getConnection')
                ->will($this->returnValue($connMock));

            $entityManagerMock->expects($this->once())->method('flush');
            $entityManagerMock->expects($this->once())->method('clear');
            $entityManagerMock->expects($this->once())->method('commit');
        } else {
            $entityManagerMock->expects($this->once())
                ->method('flush')
                ->willThrowException(new \RuntimeException("Some doctrine Exception"));
            $entityManagerMock->expects($this->once())->method('rollback');
        }

        return $entityManagerMock;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getIteratedResultMock()
    {
        $iteratorMock   = $this->getMock('Oro\Bundle\DataGridBundle\Datasource\Orm\IterableResultInterface');

        $iteratorMock->expects($this->at(0))
            ->method('rewind');

        $iteratorMock->expects($this->at(1))
            ->method('valid')
            ->will($this->returnValue(true));


        $iteratorMock->expects($this->at(2))
            ->method('current')
            ->will($this->returnValue(new ResultRecord(['id' => 1])));

        $iteratorMock->expects($this->at(3))
            ->method('next');

        $iteratorMock->expects($this->at(4))
            ->method('valid')
            ->will($this->returnValue(true));

        $iteratorMock->expects($this->at(5))
            ->method('current')
            ->will($this->returnValue(new ResultRecord(['id' => 2])));

        return $iteratorMock;
    }
}
