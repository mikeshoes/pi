<?php
/**
 * Pi Engine (http://piengine.org)
 *
 * @link            http://code.piengine.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://piengine.org
 * @license         http://piengine.org/license.txt BSD 3-Clause License
 * @package         View
 */

namespace Pi\View\Helper;

use Module\System\Menu;
use Pi;
use Pi\Application\Bootstrap\Resource\AdminMode;
use Laminas\View\Helper\AbstractHelper;

/**
 * Back-office navigation helper
 *
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
class AdminNav extends AbstractHelper
{
    /** @var string Module name */
    protected $module;

    /**
     * Invoke for helper
     *
     * @param string $module
     * @return self
     */
    public function __invoke($module = '')
    {
        $this->module = $module ?: Pi::service('module')->current();

        return $this;
    }

    /**
     * Get back-office mode render
     *
     * @param string $class
     *
     * @return string
     */
    public function modes($class = '')
    {
        $mode  = $_SESSION['PI_BACKOFFICE']['mode'];
        $modes = Menu::modes($mode);

        $pattern
                 = <<<'EOT'
<li class="nav-item %s%s">
    <a class="nav-link" href="%s">
        <i class="%s"></i>
        <span class="pi-mode-text">%s</span>
    </a>
</li>
EOT;
        $class   = $class ?: 'nav';
        $content = sprintf('<ul class="%s">', $class);
        foreach ($modes as $mode) {
            $content .= sprintf(
                $pattern,
                $mode['active'] ? 'active' : '',
                $mode['link'] ? '' : 'disabled',
                $mode['link'] ?: 'javascript:void(0)',
                $mode['icon'],
                $mode['label']
            );
        }

        $content .= '</ul>';

        return $content;
    }

    /**
     * Get back-office side menu
     *
     * @param string $class
     *
     * @return string
     */
    public function main($class = '')
    {
        $module = $this->module ?: Pi::service('module')->currrent();
        $mode   = $_SESSION['PI_BACKOFFICE']['mode'];

        $patternModule
                 = <<<'EOT'
<li class="nav-item w-100">
    <a class="nav-link %s" href="%s">
        <i class="fa %s"></i>
        <span class="pi-modules-nav-text">%s</span>
    </a>
</li>
EOT;
        $patternCategory
                 = <<<'EOT'
<li class="nav-item w-100 category">
    <a class="nav-link">
        <i class="far %s text-muted"></i>
        <span class="pi-modules-nav-category text-muted">%s</span>
    </a>
</li>
EOT;
        $pattern = [
            'module'   => $patternModule,
            'category' => $patternCategory,
        ];

        $buildContent = function ($navigation) use ($pattern) {
            $content = '';
            foreach ($navigation as $id => $category) {
                if (empty($category['modules'])) {
                    continue;
                }
                if (!empty($category['label'])) {
                    $content .= sprintf(
                        $pattern['category'],
                        $category['icon'] ?: 'far fa-square',
                        $category['label']
                    );
                }
                foreach ($category['modules'] as $item) {
                    $content .= sprintf(
                        $pattern['module'],
                        $item['active'] ? 'active' : '',
                        $item['href'],
                        $item['icon'] ?: 'fa-th',
                        $item['label']
                    );
                }
            }

            return $content;
        };

        $class   = $class ?: 'nav flex-column';
        $content = sprintf('<ul class="%s">', $class);
        // Get manage mode navigation
        if (AdminMode::MODE_ADMIN == $mode && 'system' == $module) {
            $routeMatch = Pi::engine()->application()->getRouteMatch();
            $params     = $routeMatch->getParams();
            if (empty($params['name'])) {
                $params['name'] = $_SESSION['PI_BACKOFFICE']['module'] ?: 'system';
            }
            $navigation = Menu::mainComponent(
                $params['name'],
                $params['controller']
            );
            $content    .= $buildContent($navigation);

            // Get operation mode navigation
        } elseif (AdminMode::MODE_ACCESS == $mode) {
            $navigation = Menu::mainOperation($module);
            $content    .= $buildContent($navigation);
        }

        $content .= '</ul>';

        return $content;
    }

    /**
     * Get back-office top menu
     *
     * @param array|string $options
     *
     * @return string
     */
    public function top($options = [])
    {
        $module = $this->module ?: Pi::service('module')->currrent();
        $mode   = $_SESSION['PI_BACKOFFICE']['mode'];

        if (is_string($options)) {
            $options = ['ulClass' => $options];
        }
        if (!isset($options['ulClass'])) {
            $options['ulClass'] = 'nav nav-tabs';
        }

        $navigation = '';
        // Managed components
        if (AdminMode::MODE_ADMIN == $mode && 'system' == $module) {
            $currentModule = $_SESSION['PI_BACKOFFICE']['module'];
            $navigation    = Menu::subComponent($currentModule, $options);
            // Module operations
        } elseif (AdminMode::MODE_ACCESS == $mode) {
            if (!isset($options['sub'])) {
                $options['sub'] = [
                    'ulClass'  => 'nav nav-pills',
                    'maxDepth' => 0,
                ];
            }
            list($parent, $leaf) = Menu::subOperation($module, $options);
            $navigation = $parent . $leaf;
        }

        return $navigation;
    }
}
