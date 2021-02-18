<?php
/*
* This file is part of the resource-lock-bundle package.
*
* (c) Wojciech Ciolko <wojciech.ciolko@aboutcoders.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Abc\Bundle\ResourceLockBundle\Model;

use Abc\Bundle\ResourceLockBundle\Exception\LockException;

/**
 * @author Wojciech Ciolko <w.ciolko@aboutcoders.com>
 */
interface LockInterface
{
    /**
     * @param string $name Resource name
     * @return $mixed|void LockObject
     * @throws LockException Threw when lock can not be set or already set
     */
    public function lock($name);

    /**
     * @param string $name Resource name
     * @return boolean
     */
    public function isLocked($name);

    /**
     * @param string $name Resource name
     * @return boolean
     */
    public function release($name);
}