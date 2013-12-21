<?php
/**
 * Pi Engine (http://pialog.org)
 *
 * @link            http://code.pialog.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://pialog.org
 * @license         http://pialog.org/license.txt New BSD License
 */

namespace Module\Article;

use Pi;
use Pi\Search\AbstractSearch;
use Pi\Db\Sql\Where;
use Pi\Application\Model\Model;

/**
 * Class for module search
 *
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
class Search extends AbstractSearch
{
    /**
     * {@inheritDoc}
     */
    protected $table = 'article';

    /**
     * {@inheritDoc}
     */
    protected $searchIn = array(
        'subject',
        'subtitle',
        'summary',
        'content'
    );

    /**
     * {@inheritDoc}
     */
    protected $meta = array(
        'id'            => 'id',
        'summary'       => 'content',
        'time_publish'  => 'time',
        'uid'           => 'uid',
    );

    /**
     * {@inheritDoc}
     */
    protected function buildLink(array $item)
    {
        $link = Pi::service('url')->assemble(
            'article-article',
            array('id' => $item['id'])
        );

        return $link;
    }
}