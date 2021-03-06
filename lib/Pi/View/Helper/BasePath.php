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

use Pi;
use Laminas\View\Helper\AbstractHelper;

/**
 * Helper for base path
 *
 * Usage inside a phtml template
 *
 * ```
 *  $this->basePath();
 *  $this->basepath($file);
 * ```
 *
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
class BasePath extends AbstractHelper
{
    /**
     * Get base path
     *
     * @param   string $file
     * @return  string
     */
    public function __invoke($file = null)
    {
        return Pi::url('www') . ((null === $file) ? '' : '/' . $file);
    }
}
