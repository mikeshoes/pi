<?php
/**
 * Pi Engine (http://piengine.org)
 *
 * @link            http://code.piengine.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://piengine.org
 * @license         http://piengine.org/license.txt BSD 3-Clause License
 */

namespace Module\Demo\Api;

use Pi;
use Pi\Search\AbstractSearch;

class Search extends AbstractSearch
{
    /**
     * {@inheritDoc}
     */
    protected $module = 'demo';

    /**
     * {@inheritDoc}
     */
    public function query(
        $terms,
        $limit = 0,
        $offset = 0,
        array $condition = []
    )
    {
        $results = [];
        $max     = 1000;
        $count   = 0;
        for ($i = $offset; $i < $max; $i++) {
            if (++$count > $limit) break;
            $item      = [
                'uid'     => 1,
                'time'    => time(),
                'url'     => Pi::service('url')->assemble(
                    'default',
                    [
                        'module'     => 'demo',
                        'controller' => 'search',
                        'q'          => 'test-' . $i,
                    ]
                ),
                'title'   => sprintf(__('Test term %d'), $i),
                'content' => sprintf(__('Some content for term %d'), $i),
            ];
            $results[] = $item;
        }

        $result = $this->buildResult($max, $results);

        return $result;
    }
}
