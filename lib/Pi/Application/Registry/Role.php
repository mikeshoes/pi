<?php
/**
 * Pi Engine (http://piengine.org)
 *
 * @link            http://code.piengine.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://piengine.org
 * @license         http://piengine.org/license.txt BSD 3-Clause License
 * @package         Registry
 */

namespace Pi\Application\Registry;

use Pi;

/**
 * Role list
 *
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
class Role extends AbstractRegistry
{
    /**
     * {@inheritDoc}
     */
    protected function loadDynamic($options = [])
    {
        $result = [];
        $model  = Pi::model('role');
        $where  = ['active' => 1];
        if (!empty($options['section'])) {
            $where['section'] = $options['section'];
        }
        $select = $model->select();
        $select->order(['section', 'title ASC']);
        $select->where($where);
        $rowset = $model->selectWith($select);
        foreach ($rowset as $row) {
            $result[$row['name']] = [
                'section' => $row['section'],
                'title'   => $row['title'],
                'id'      => (int)$row['id'],
            ];
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     * @param string $section
     */
    public function read($section = '')
    {
        $options = compact('section');
        $data    = $this->loadData($options);
        /*
        if ($section) {
            $data = $data[$section];
        }
        */

        return $data;
    }

    /**
     * {@inheritDoc}
     * @param string $section
     */
    public function create($section = '')
    {
        $this->clear($section);
        $this->read($section);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function setNamespace($meta = '')
    {
        return parent::setNamespace('');
    }

    /**
     * {@inheritDoc}
     */
    public function clear($namespace = '')
    {
        parent::clear('');

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function flush()
    {
        $this->clear('');

        return $this;
    }
}
