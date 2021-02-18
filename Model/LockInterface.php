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
     * @param string $name
     * @param int    $autoReleaseTime Time in seconds - default value is 0 - when value is 0 or smaller then automatic release lock feature is disabled
     * @return bool
     */
    public function isLocked($name, int $autoReleaseTime = 0);

    /**
     * @param string $name Resource name
     * @return boolean
     */
    public function release($name);
}