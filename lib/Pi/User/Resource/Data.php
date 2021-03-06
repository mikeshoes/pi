<?php
/**
 * Pi Engine (http://piengine.org)
 *
 * @link            http://code.piengine.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://piengine.org
 * @license         http://piengine.org/license.txt BSD 3-Clause License
 */

namespace Pi\User\Resource;

use Pi;
use Pi\Db\RowGateway\RowGateway;

/**
 * User data handler
 *
 * Data APIs:
 *
 * - get($uid, $name, $returnArray)
 * - set($uid, $name, $value, $module, $time)
 * - setInt($uid, $name, $value, $module, $time)
 * - increment($uid, $name, $value, $module, $time)
 * - delete($uid, $name)
 * - find($conditions)
 *
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
class Data extends AbstractResource
{
    /**
     * Get user data
     *
     * @param int|int[] $uid
     * @param string $name
     * @param bool $returnArray
     * @param string $module
     *
     * @return int|mixed|array
     */
    public function get($uid, $name, $returnArray = false, $module = '')
    {
        $uids = (array)$uid;
        array_walk($uids, 'intval');
        $result = false;

        $getValue = function ($row) use ($returnArray) {
            $result = false;
            if ($row) {
                if (!$row['expire'] || $row['expire'] > time()) {
                    if (null !== $row['value_int']) {
                        $value = (int)$row['value_int'];
                    } elseif (null !== $row['value']) {
                        $value = $row['value'];
                    } else {
                        $value = $row['value_multi'];
                    }
                    if (!$returnArray) {
                        $result = $value;
                    } else {
                        $result = [
                            'time'   => $row['time'],
                            'value'  => $value,
                            'module' => $row['module'],
                        ];
                    }
                }
            }

            return $result;
        };

        $where  = [
            'uid'    => $uids,
            'name'   => $name,
            'module' => $module ?: Pi::service('module')->current(),
        ];
        $rowset = Pi::model('user_data')->select($where);
        if (is_scalar($uid)) {
            $row    = $rowset->current();
            $result = $getValue($row);
        } else {
            foreach ($rowset as $row) {
                $result[(int)$row['uid']] = $getValue($row, $returnArray);
            }
        }

        return $result;
    }

    /**
     * Delete user data
     *
     * @param int|int[] $uid
     * @param string $name
     * @param string $module
     *
     * @return bool
     */
    public function delete($uid, $name, $module = '')
    {
        $uids = (array)$uid;
        array_walk($uids, 'intval');

        $where = [
            'uid'    => $uids,
            'name'   => $name,
            'module' => $module ?: Pi::service('module')->current(),
        ];
        try {
            Pi::model('user_data')->delete($where);
            $result = true;
        } catch (\Exception $e) {
            $result = false;
        }

        return $result;
    }

    /**
     * Write user data
     *
     * @param int|array $uid
     * @param string $name
     * @param mixed|int $value
     * @param string $module
     * @param int $expire
     *
     * @return bool
     */
    public function set(
        $uid,
        $name = null,
        $value = null,
        $module = '',
        $expire = 0
    )
    {
        if (is_array($uid)) {
            $id = isset($uid['uid']) ? (int)$uid['uid'] : 0;
            extract($uid);
            $uid = $id;
        }
        $module              = $module ?: Pi::service('module')->current();
        $time                = time();
        $expire              = $expire ? $time + (int)$expire : 0;
        $vars                = [
            'uid'    => (int)$uid,
            'name'   => $name,
            'expire' => $expire,
            'module' => $module,
            'time'   => $time,
        ];
        $vars['value']       = null;
        $vars['value_int']   = null;
        $vars['value_multi'] = null;
        if (is_int($value)) {
            $vars['value_int'] = $value;
        } elseif (is_scalar($value)) {
            $vars['value'] = $value;
        } else {
            $vars['value_multi'] = $value;
        }

        $where = [
            'uid'    => (int)$uid,
            'name'   => $name,
            'module' => $module,
        ];
        $row   = Pi::model('user_data')->select($where)->current();
        if ($row) {
            $row->assign($vars);
        } else {
            $row = Pi::model('user_data')->createRow($vars);
        }
        try {
            $row->save();
            $result = true;
        } catch (\Exception $e) {
            $result = false;
        }

        return $result;
    }

    /**
     * Find a data subject to conditions
     *
     * @param array $conditions
     * @param bool $returnObject
     *
     * @return array|RowGateway|bool
     */
    public function find(array $conditions, $returnObject = false)
    {
        $result = false;
        if (isset($conditions['value'])) {
            if (is_int($conditions['value'])) {
                $conditions['value_int'] = $conditions['value'];
                unset($conditions['value']);
            }
        }
        $rowset = Pi::model('user_data')->select($conditions);
        $row    = $rowset->current();
        if ($row) {
            if (!$row['expire'] || $row['expire'] > time()) {
                $result = $returnObject ? $row : $row->toArray();
            }
        }

        return $result;
    }

    /**
     * Write user integer data
     *
     * @param int|array $uid
     * @param string $name
     * @param int $value
     * @param string $module
     * @param int $expire
     *
     * @return bool
     */
    public function setInt(
        $uid,
        $name = null,
        $value = 0,
        $module = '',
        $expire = 0
    )
    {
        if (is_array($uid) && isset($uid['value'])) {
            $uid['value'] = (int)$uid['value'];
        }
        $value = (int)$value;

        return $this->set($uid, $name, $value, $expire, $module);
    }

    /**
     * Increment/decrement an int data
     *
     * Positive to increment or negative to decrement; 0 to reset!
     *
     * @param int|int[] $uid
     * @param string $name
     * @param int $value
     * @param string $module
     * @param int $expire
     *
     * @return bool
     */
    public function increment($uid, $name, $value, $module = '', $expire = 0)
    {
        $value  = (int)$value;
        $module = $module ?: Pi::service('module')->current();
        $where  = [
            'uid'    => (int)$uid,
            'name'   => $name,
            'module' => $module,
        ];
        $row    = Pi::model('user_data')->select($where)->current();

        // Insert new value
        if (!$row) {
            $result = $this->setInt($uid, $name, $value, $expire, $module);
            // Reset
        } elseif (0 == $value || null === $row['value_int']) {
            $row['value_int'] = $value;
            $row['expire']    = $expire ? time() + $expire : 0;
            try {
                $row->save();
                $result = true;
            } catch (\Exception $e) {
                $result = false;
            }
            // Increase/Decrease
        } else {
            $model = Pi::model('user_data');
            if (0 < $value) {
                $string = '`value_int`=`value_int`+' . $value;
            } else {
                $string = '`value_int`=`value_int`-' . abs($value);
            }
            $expire = $expire ? time() + $expire : 0;
            $string .= ', `expire`=\'' . $expire . '\'';
            $sql    = 'UPDATE ' . $model->getTable()
                . ' SET ' . $string
                . ' WHERE `uid`=' . $uid
                . ' AND `name`=\'' . $name . '\'';
            try {
                Pi::db()->query($sql);
                $result = true;
            } catch (\Exception $e) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Garbage collection
     */
    public function gc()
    {
        Pi::model('user_data')->delete([
            'expire <> ?' => 0,
            'expire < ?'  => time(),
        ]);
    }
}
