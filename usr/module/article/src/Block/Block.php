<?php
/**
 * Pi Engine (http://piengine.org)
 *
 * @link         http://code.piengine.org for the Pi Engine source repository
 * @copyright    Copyright (c) Pi Engine http://piengine.org
 * @license      http://piengine.org/license.txt BSD 3-Clause License
 */

namespace Module\Article\Block;

use Module\Article\Entity;
use Module\Article\Media;
use Module\Article\Stats;
use Module\Article\Topic;
use Pi;
use Laminas\Db\Sql\Expression;

/**
 * Block class for providing article blocks
 *
 * @author Zongshu Lin <lin40553024@163.com>
 */
class Block
{
    /**
     * List all categories and its children
     *
     * @param array $options Block parameters
     * @param string $module Module name
     * @return boolean
     */
    public static function allCategories($options = [], $module = null)
    {
        if (empty($module)) {
            return false;
        }

        $maxTopCount = $options['top-category'];
        $maxSubCount = $options['sub-category'];
        $route       = 'article';

        $categories = Pi::api('api', $module)->getCategoryList(
            ['is-tree' => true]
        );

        $allItems = static::canonizeCategories(
            $categories['child'],
            ['route' => $route]
        );

        $i = 0;
        foreach ($allItems as $id => &$item) {
            if (++$i > $maxTopCount) {
                unset($allItems[$id]);
            }
            $j = 0;
            if (!isset($item['child'])) {
                continue;
            }
            foreach (array_keys($item['child']) as $subId) {
                if (++$j > $maxSubCount) {
                    unset($item['child'][$subId]);
                }
            }
        }

        return [
            'items'  => $allItems,
            'target' => $options['target'],
        ];
    }

    /**
     * List all categories
     *
     * @param array $options Block parameters
     * @param string $module Module name
     * @return boolean
     */
    public static function categoryList($options = [], $module = null)
    {
        if (empty($module)) {
            return false;
        }

        $route = 'article';

        // Get all categories
        $categories = [
            'all' => [
                'id'    => 0,
                'title' => __('All articles'),
                'depth' => 0,
                'image' => '',
                'url'   => Pi::service('url')->assemble(
                    $route,
                    [
                        'module'     => $module,
                        'controller' => 'list',
                        'action'     => 'all',
                        'list'       => 'all',
                    ]
                ),
            ],
        ];
        $rowset     = Pi::model('category', $module)->enumerate(null, null, true);
        foreach ($rowset as $row) {
            if ('root' == $row['name']) {
                continue;
            }
            $url                    = Pi::service('url')->assemble($route, [
                'module'     => $module,
                'controller' => 'category',
                'action'     => 'list',
                'category'   => $row['slug'] ?: $row['id'],
            ]);
            $categories[$row['id']] = [
                'id'    => $row['id'],
                'title' => $row['title'],
                'depth' => $row['depth'],
                'image' => $row['image'],
                'url'   => $url,
            ];
        }

        $params = Pi::service('url')->getRouteMatch()->getParams();

        return [
            'items'    => $categories,
            'options'  => $options,
            'category' => $params['category'],
        ];
    }

    /**
     * List hot categories
     *
     * @param array $options Block parameters
     * @param string $module Module name
     * @return boolean
     */
    public static function hotCategories($options = [], $module = null)
    {
        if (empty($module)) {
            return false;
        }

        $limit    = (int)$options['list-count'];
        $limit    = $limit < 0 ? 0 : $limit;
        $day      = (int)$options['day-range'];
        $endDay   = time();
        $startDay = $endDay - $day * 3600 * 24;

        // Get category IDs
        $where = [
            'time_publish > ?'  => $startDay,
            'time_publish <= ?' => $endDay,
        ];

        $modelArticle = Pi::model('article', $module);
        $select       = $modelArticle->select()
            ->where($where)
            ->columns(['category', 'count' => new Expression('count(*)')])
            ->group(['category'])
            ->offset(0)
            ->limit($limit)
            ->order('count DESC');
        $rowArticle   = $modelArticle->selectWith($select);
        $categoryIds  = [0];
        foreach ($rowArticle as $row) {
            $categoryIds[] = $row['category'];
        }

        // Get category Info
        //$route = Pi::api('api', $module)->getRouteName();
        $where       = ['id' => $categoryIds];
        $rowCategory = Pi::model('category', $module)->select($where);
        $categories  = [];
        foreach ($rowCategory as $row) {
            $categories[$row->id]['title'] = $row->title;
            $categories[$row->id]['url']   = Pi::service('url')->assemble(
                'article',
                [
                    'module'   => $module,
                    'category' => $row->slug ?: $row->id,
                ]
            );
        }

        return [
            'categories' => $categories,
            'target'     => $options['target'],
        ];
    }

    /**
     * List newest published articles
     *
     * @param array $options
     * @param string $module
     * @return boolean
     */
    public static function newestPublishedArticles(
        $options = [],
        $module = null
    )
    {
        if (empty($module)) {
            return false;
        }

        $params = Pi::service('url')->getRouteMatch()->getParams();

        $config = Pi::config('', $module);
        $image  = $config['default_feature_thumb'];
        $image  = Pi::service('asset')->getModuleAsset($image, $module);

        $postCategory = isset($params['category']) ? $params['category'] : 0;
        $postTopic    = isset($params['topic']) ? $params['topic'] : 0;

        $category = $options['category'] ? $options['category'] : $postCategory;
        $topic    = $options['topic'] ? $options['topic'] : $postTopic;
        if (!is_numeric($topic)) {
            $topic = Pi::model('topic', $module)->slugToId($topic);
        }
        $limit   = ($options['list-count'] <= 0) ? 10 : $options['list-count'];
        $page    = 1;
        $order   = 'time_update DESC, time_publish DESC';
        $columns = ['subject', 'summary', 'time_publish', 'image'];
        $where   = [];
        if (!empty($category)) {
            $category          = Pi::model('category', $module)
                ->getDescendantIds($category);
            $where['category'] = $category;
        }
        if (!empty($options['is-topic'])) {
            if (!empty($topic)) {
                $where['topic'] = $topic;
            }
            $articles = Topic::getTopicArticles(
                $where,
                $page,
                $limit,
                $columns,
                $order,
                $module
            );
        } else {
            $articles = Entity::getAvailableArticlePage(
                $where,
                $page,
                $limit,
                $columns,
                $order,
                $module
            );
        }

        foreach ($articles as &$article) {
            $article['subject'] = mb_substr(
                $article['subject'],
                0,
                $options['max_subject_length'],
                'UTF-8'
            );
            $article['summary'] = mb_substr(
                $article['summary'],
                0,
                $options['max_summary_length'],
                'UTF-8'
            );
            $article['image']   = $article['image']
                ? Media::getThumbFromOriginal(Pi::url($article['image']))
                : $image;
        }

        return [
            'articles' => $articles,
            'target'   => $options['target'],
            'elements' => (array)$options['element'],
            'config'   => $config,
            'column'   => $options['column-number'],
            'rows'     => $options['description_rows'],
        ];
    }

    /**
     * List articles defined by user.
     *
     * @param array $options
     * @param string $module
     * @return boolean
     */
    public static function customArticleList($options = [], $module = null)
    {
        if (!$module) {
            return false;
        }

        $config = Pi::config('', $module);
        $image  = $config['default_feature_thumb'];
        $image  = Pi::service('asset')->getModuleAsset($image, $module);

        $columns = ['subject', 'summary', 'time_publish', 'image'];
        $ids     = explode(',', $options['articles']);
        foreach ($ids as &$id) {
            $id = trim($id);
        }
        $where    = ['id' => $ids];
        $articles = Entity::getAvailableArticlePage(
            $where,
            1,
            10,
            $columns,
            null,
            $module
        );

        foreach ($articles as &$article) {
            $article['subject'] = mb_substr(
                $article['subject'],
                0,
                $options['max_subject_length'],
                'UTF-8'
            );
            $article['summary'] = mb_substr(
                $article['summary'],
                0,
                $options['max_summary_length'],
                'UTF-8'
            );
            $article['image']   = $article['image']
                ? Media::getThumbFromOriginal(Pi::url($article['image']))
                : $image;
        }

        return [
            'articles' => $articles,
            'target'   => $options['target'],
            'elements' => (array)$options['element'],
            'column'   => $options['column-number'],
            'config'   => $config,
            'rows'     => $options['description_rows'],
        ];
    }

    /**
     * Export a search form.
     *
     * @param array $options
     * @param string $module
     * @return boolean
     */
    public static function simpleSearch($options = [], $module = null)
    {
        if (!$module) {
            return false;
        }

        return [
            'url' => Pi::service('url')->assemble(
                'default',
                [
                    'module'     => $module,
                    'controller' => 'search',
                    'action'     => 'simple',
                ]
            ),
        ];
    }

    /**
     * Count all article according to submitter
     *
     * @param array $options
     * @param string $module
     * @return boolean
     */
    public static function submitterStatistics($options = [], $module = null)
    {
        if (!$module) {
            return false;
        }

        $limit       = ($options['list-count'] <= 0) ? 10 : $options['list-count'];
        $time        = time();
        $today       = strtotime(date('Y-m-d', $time));
        $tomorrow    = $today + 24 * 3600;
        $week        = $tomorrow - 24 * 3600 * 7;
        $month       = $tomorrow - 24 * 3600 * 30;
        $daySets     = Stats::getSubmittersInPeriod($today, $tomorrow, $limit, $module);
        $weekSets    = Stats::getSubmittersInPeriod($week, $tomorrow, $limit, $module);
        $monthSets   = Stats::getSubmittersInPeriod($month, $tomorrow, $limit, $module);
        $historySets = Stats::getSubmittersInPeriod(0, $tomorrow, $limit, $module);

        return [
            'day'     => $daySets,
            'week'    => $weekSets,
            'month'   => $monthSets,
            'history' => $historySets,
        ];
    }

    /**
     * List newest topics.
     *
     * @param array $options
     * @param string $module
     * @return boolean
     */
    public static function newestTopic($options = [], $module = null)
    {
        if (!$module) {
            return false;
        }

        $limit  = ($options['list-count'] <= 0) ? 10 : $options['list-count'];
        $order  = 'id DESC';
        $topics = Topic::getTopics([], 1, $limit, null, $order, $module);
        $config = Pi::config('', $module);
        $image  = Pi::service('asset')
            ->getModuleAsset($config['default_topic_thumb'], $module);

        foreach ($topics as &$topic) {
            $topic['title']       = mb_substr(
                $topic['title'],
                0,
                $options['max_title_length'],
                'UTF-8'
            );
            $topic['description'] = mb_substr(
                $topic['description'],
                0,
                $options['max_description_length'],
                'UTF-8'
            );
            $topic['image']       = $topic['image']
                ? Media::getThumbFromOriginal(Pi::url($topic['image']))
                : $image;
        }

        return [
            'items'  => $topics,
            'target' => $options['target'],
            'config' => $config,
        ];
    }

    /**
     * List hot articles by visit count
     *
     * @param array $options
     * @param string $module
     * @return boolean
     */
    public static function hotArticles($options = [], $module = null)
    {
        if (!$module) {
            return false;
        }

        $limit  = isset($options['list-count'])
            ? (int)$options['list-count'] : 10;
        $config = Pi::config('', $module);
        $image  = $config['default_feature_thumb'];
        $image  = Pi::service('asset')->getModuleAsset($image, $module);
        $day    = $options['day-range'] ? intval($options['day-range']) : 7;

        if ($options['is-topic']) {
            $params = Pi::service('url')->getRouteMatch()->getParams();
            if (is_string($params)) {
                $params['topic'] = Pi::model('topic', $module)
                    ->slugToId($params['topic']);
            }
            $articles = Topic::getVisitsRecently(
                $day,
                $limit,
                null,
                isset($params['topic']) ? $params['topic'] : null,
                $module
            );
        } else {
            $articles = Entity::getVisitsRecently($day, $limit, null, $module);
        }

        foreach ($articles as &$article) {
            $article['subject'] = mb_substr(
                $article['subject'],
                0,
                $options['max_subject_length'],
                'UTF-8'
            );
            $article['summary'] = mb_substr(
                $article['summary'],
                0,
                $options['max_summary_length'],
                'UTF-8'
            );
            $article['image']   = $article['image']
                ? Media::getThumbFromOriginal(Pi::url($article['image']))
                : $image;
        }

        return [
            'articles' => $articles,
            'target'   => $options['target'],
            'elements' => (array)$options['element'],
            'column'   => $options['column-number'],
            'config'   => $config,
            'rows'     => $options['description_rows'],
        ];
    }

    /**
     * List custom articles and with a slideshow besides article list
     *
     * @param array $options
     * @param string $module
     * @return boolean
     */
    public static function recommendedSlideshow(
        $options = [],
        $module = null
    )
    {
        if (!$module) {
            return false;
        }

        // Getting custom article list
        $columns = ['subject', 'summary', 'time_publish', 'image'];
        $ids     = explode(',', $options['articles']);
        foreach ($ids as &$id) {
            $id = trim($id);
        }
        $where    = ['id' => $ids];
        $articles = Entity::getAvailableArticlePage(
            $where,
            1,
            10,
            $columns,
            null,
            $module
        );

        $config = Pi::config('', $module);
        $image  = $config['default_feature_thumb'];
        $image  = Pi::service('asset')->getModuleAsset($image, $module);
        foreach ($articles as &$article) {
            $article['subject'] = mb_substr(
                $article['subject'],
                0,
                $options['max_subject_length'],
                'UTF-8'
            );
            $article['summary'] = mb_substr(
                $article['summary'],
                0,
                $options['max_summary_length'],
                'UTF-8'
            );
            $article['image']   = $article['image']
                ? Media::getThumbFromOriginal(Pi::url($article['image']))
                : $image;
        }

        // Getting image link url
        $urlRows    = explode('\n', $options['image-link']);
        $imageLinks = [];
        foreach ($urlRows as $row) {
            list($id, $url) = explode(':', trim($row), 2);
            $imageLinks[trim($id)] = trim($url);
        }

        // Fetching image ID
        $images   = explode(',', $options['images']);
        $imageIds = [];
        foreach ($images as $key => &$image) {
            $image = trim($image);
            if (is_numeric($image)) {
                $imageIds[] = $image;
            } else {
                $url   = $image ?: 'image/default-recommended.png';
                $image = [
                    'url'         => Pi::service('asset')->getModuleAsset($url, $module),
                    'link'        => $imageLinks[$key + 1],
                    'title'       => _b('This is default recommended image'),
                    'description' => _b('You should to add your own images and its title and description!'),
                ];
            }
        }

        if (!empty($imageIds)) {
            $images = [];
            $rowset = Pi::model('media', $module)->select(['id' => $imageIds]);
            foreach ($rowset as $row) {
                $id       = $row['id'];
                $link     = isset($imageLinks[$id]) ? $imageLinks[$id] : '';
                $images[] = [
                    'url'         => Pi::url($row['url']),
                    'link'        => $link,
                    'title'       => $row['title'],
                    'description' => $row['description'],
                ];
            }
        }

        return [
            'articles' => $articles,
            'target'   => $options['target'],
            'style'    => $options['block-style'],
            'elements' => (array)$options['element'],
            'height'   => $options['image-height'],
            'images'   => $images,
            'config'   => Pi::config('', $module),
            'rows'     => $options['description_rows'],
        ];
    }

    /**
     * Show RSS link
     *
     * @param array $options
     * @param string $module
     * @return boolean
     */
    public static function rss($options = [], $module = null)
    {
        if (!$module) {
            return false;
        }

        $url = Pi::service('asset')->getModuleAsset(
            $options['default_image'],
            $module
        );

        return [
            'target'      => $options['target'],
            'description' => $options['description'],
            'url'         => $url,
        ];
    }

    /**
     * Added all sub-categories as children array of top category.
     *
     * @param array $categories
     * @param array $options
     * @return array
     */
    protected static function canonizeCategories(
        $categories,
        $options = []
    )
    {
        $result = [];
        foreach ($categories as $category) {
            $result[$category['id']] = [
                'title' => $category['title'],
                'depth' => $category['depth'],
                'url'   => Pi::service('url')->assemble(
                    $options['route'],
                    [
                        'category' => $category['slug'] ?: $category['id'],
                    ]
                ),
            ];
            if (isset($category['child'])) {
                $children = self::canonizeCategories(
                    $category['child'],
                    $options
                );
                if ($category['depth'] > 1) {
                    $result = $result + $children;
                } else {
                    $result[$category['id']]['child'] = $children;
                }
            }
        }

        return $result;
    }
}
