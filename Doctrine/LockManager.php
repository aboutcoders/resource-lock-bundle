<?php
/*
* This file is part of the resource-lock-bundle package.
*
* (c) Wojciech Ciolko <wojciech.ciolko@aboutcoders.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Abc\Bundle\ResourceLockBundle\Doctrine;

use Abc\Bundle\ResourceLockBundle\Exception\LockException;
use Abc\Bundle\ResourceLockBundle\Model\LockManager as BaseLockManager;
use Abc\Bundle\ResourceLockBundle\Model\ResourceLock;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\DBAL\Exception\ServerException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * @author Wojciech Ciolko <wojciech.ciolko@aboutcoders.com>
 */
class LockManager extends BaseLockManager
{
    /** @var ObjectManager */
    protected $objectManager;
    /** @var string */
    protected $class;
    /** @var ObjectRepository */
    protected $repository;
    /** @var string Lock prefix for manager */
    protected $prefix;

    /**
     * @param ObjectManager $om     Doctrine object manager
     * @param string        $class  Entity class name
     * @param string        $prefix Prefix of a lock record
     */
    public function __construct(ObjectManager $om, $class, $prefix = 'abc-lock')
    {
        $this->objectManager = $om;
        $this->repository    = $om->getRepository($class);
        $this->prefix        = $prefix;
        $metadata            = $om->getClassMetadata($class);
        $this->class         = $metadata->getName();
    }

    /**
     * @param string $name
     * @return object
     */
    private function findByName($name)
    {
        return $this->repository->findOneBy(['name' => $name]);
    }

    public function lock($name)
    {
        $nameWithPrefix = $this->getNameWithPrefix($name);
        $lock           = $this->create($nameWithPrefix);
        try {
            $this->objectManager->persist($lock);
            $this->objectManager->flush();
            return $lock;
        } catch (UniqueConstraintViolationException $e) {
            throw new LockException('Lock with provided name already exists', $e->getErrorCode(), $e);
        } catch (ServerException $e) {
            throw new LockException('Lock with provided name can not be set', $e->getErrorCode(), $e);
        }
    }

    /**
     * @param string $name
     * @param int    $autoReleaseTime Time in seconds - default value is 0 - when value is 0 or smaller then automatic release lock feature is disabled
     * @return bool
     */
    public function isLocked($name, int $autoReleaseTime = 0)
    {
        $nameWithPrefix = $this->getNameWithPrefix($name);

        /** @var ResourceLock $lock */
        $lock           = $this->findByName($nameWithPrefix);

        $actualDate = new \DateTime();
        if (isset($lock) && ($autoReleaseTime > 0) && ($lock->getCreatedAt()->modify('+' . $autoReleaseTime . ' seconds') < $actualDate)) {
            $this->release($name);
            return false;
        }

        return $lock ? true : false;
    }

    public function release($name)
    {
        $nameWithPrefix = $this->getNameWithPrefix($name);
        $lock           = $this->repository->findOneBy(['name' => $nameWithPrefix]);
        if ($lock) {
            $this->objectManager->remove($lock);
            $this->objectManager->flush();
            return true;
        }
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Get name with prefix
     *
     * @param $name string Lock name
     * @return string Lock name with prefix
     */
    private function getNameWithPrefix($name)
    {
        return $this->prefix . '-' . $name;
    }
}