<?php
/*
* This file is part of the resource-lock-bundle package.
*
* (c) Wojciech Ciolko <wojciech.ciolko@aboutcoders.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Abc\Bundle\ResourceLockBundle\Tests\Doctrine;

use Abc\Bundle\ResourceLockBundle\Doctrine\LockManager;
use Abc\Bundle\ResourceLockBundle\Entity\ResourceLock;
use Abc\Bundle\ResourceLockBundle\Model\ResourceLockInterface;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\DBAL\Driver\DriverException;
use Doctrine\DBAL\Exception\ServerException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * @author Wojciech Ciolko <wojciech.ciolko@aboutcoders.com>
 */
class LockManagerTest extends \PHPUnit_Framework_TestCase
{
    /** @var  string */
    protected $prefix = 'custom-prefix';
    /** @var string */
    private $class;
    /** @var ClassMetadata|\PHPUnit_Framework_MockObject_MockObject */
    private $classMetaData;
    /** @var ObjectManager|\PHPUnit_Framework_MockObject_MockObject */
    private $objectManager;
    /** @var ObjectRepository|\PHPUnit_Framework_MockObject_MockObject */
    private $repository;

    /** @var LockManager */
    private $subject;

    public function setUp()
    {
        $this->class         = ResourceLock::class;
        $this->classMetaData = $this->getMock(ClassMetadata::class);
        $this->objectManager = $this->getMock(ObjectManager::class);
        $this->repository    = $this->getMock(ObjectRepository::class);

        $this->objectManager->expects($this->any())
            ->method('getClassMetadata')
            ->will($this->returnValue($this->classMetaData));

        $this->classMetaData->expects($this->any())
            ->method('getName')
            ->will($this->returnValue($this->class));

        $this->objectManager->expects($this->any())
            ->method('getRepository')
            ->will($this->returnValue($this->repository));

        $this->subject = new LockManager($this->objectManager, $this->class, $this->prefix);
    }

    public function testGetClass()
    {
        $this->assertEquals($this->class, $this->subject->getClass());
    }

    public function testIsLockedWithNoExistingLockReturnsFalse()
    {
        $lockName           = 'ABC';
        $lockNameWithPrefix = $this->prefix . '-ABC';
        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with(['name' => $lockNameWithPrefix])
            ->willReturn(null);

        $result = $this->subject->isLocked($lockName);

        $this->assertFalse($result);
    }

    public function testIsLockedWithExistingLockReturnsTrue()
    {
        $lockName           = 'ABC';
        $lockNameWithPrefix = $this->prefix . '-ABC';
        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with(['name' => $lockNameWithPrefix])
            ->willReturn(new ResourceLock());

        $result = $this->subject->isLocked($lockName);

        $this->assertTrue($result);
    }

    public function testIsLockedWithExistingLockAndAutoReleasedIsSetLockExpiredReturnsFalse()
    {
        $lockName           = 'ABC';
        $lockNameWithPrefix = $this->prefix . '-ABC';

        $createdAt = new \DateTime();
        $createdAt->modify('-1 hour');

        $lockObj = new ResourceLock();
        $lockObj->setCreatedAt($createdAt);

        $this->repository->expects($this->exactly(2))
            ->method('findOneBy')
            ->with(['name' => $lockNameWithPrefix])
            ->willReturn($lockObj);

        $result = $this->subject->isLocked($lockName,1800);

        $this->assertFalse($result);
    }

    public function testIsLockedWithExistingLockAndAutoReleasedIsSetLockNotExpiredReturnsTrue()
    {
        $lockName           = 'ABC';
        $lockNameWithPrefix = $this->prefix . '-ABC';

        $createdAt = new \DateTime();
        $createdAt->modify('-1 hour');

        $lockObj = new ResourceLock();
        $lockObj->setCreatedAt($createdAt);

        $this->repository->expects($this->exactly(1))
            ->method('findOneBy')
            ->with(['name' => $lockNameWithPrefix])
            ->willReturn($lockObj);

        $result = $this->subject->isLocked($lockName,7200);

        $this->assertTrue($result);
    }

    public function testLockWithNoExistingLockReturnsTrue()
    {
        $lockName = 'ABC';
        $this->objectManager->expects($this->once())
            ->method('persist');
        $this->objectManager->expects($this->once())
            ->method('flush');

        $result = $this->subject->lock($lockName);

        $this->assertInstanceOf(ResourceLockInterface::class, $result);
    }

    public function testReleaseWithNoExistingLockReturnsFalse()
    {
        $lockName           = 'ABC';
        $lockNameWithPrefix = $this->prefix . '-ABC';
        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with(['name' => $lockNameWithPrefix])
            ->willReturn(null);

        $result = $this->subject->release($lockName);

        $this->assertFalse($result);
    }

    public function testReleaseWithExistingLockReturnsTrue()
    {
        $lockName           = 'ABC';
        $lockNameWithPrefix = $this->prefix . '-ABC';
        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with(['name' => $lockNameWithPrefix])
            ->willReturn(new ResourceLock());

        $this->objectManager->expects($this->once())
            ->method('remove');
        $this->objectManager->expects($this->once())
            ->method('flush');

        $result = $this->subject->release($lockName);

        $this->assertTrue($result);
    }

    /**
     * @expectedException \Abc\Bundle\ResourceLockBundle\Exception\LockException
     * @expectedExceptionMessage Lock with provided name already exists
     */
    public function testLockWithExistingLocThrowsException()
    {
        $lockName = 'ABC';

        $ex = $this->getMock(DriverException::class);
        $this->objectManager->expects($this->once())
            ->method('persist')->willThrowException(
                new UniqueConstraintViolationException('Unique', $ex)
            );

        $this->subject->lock($lockName);
    }

    /**
     * @expectedException \Abc\Bundle\ResourceLockBundle\Exception\LockException
     * @expectedExceptionMessage Lock with provided name can not be set
     */
    public function testLockThrowsServerException()
    {
        $lockName = 'ABC';

        $ex = $this->getMock(DriverException::class);
        $this->objectManager->expects($this->once())
            ->method('persist')->willThrowException(
                new ServerException('ServerException', $ex)
            );

        $this->subject->lock($lockName);
    }
}