<?php
/**
 * Pi Engine (http://piengine.org)
 *
 * @link            http://code.piengine.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://piengine.org
 * @license         http://piengine.org/license.txt BSD 3-Clause License
 */

namespace Pi\Authentication\Adapter\DbTable;

use Pi\Authentication\Adapter\AdapterInterface as BaseInterface;
use Laminas\Db\Adapter\Adapter as DbAdapter;

/**
 * Pi authentication DbTable adapter interface
 *
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
interface AdapterInterface extends BaseInterface
{
    /**
     * Set Db adapter
     *
     * @param DbAdapter $adapter
     * @return void
     */
    public function setDbAdapter(DbAdapter $adapter = null);
}
