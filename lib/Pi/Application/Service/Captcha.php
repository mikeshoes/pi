<?php
/**
 * Pi Engine (http://piengine.org)
 *
 * @link            http://code.piengine.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://piengine.org
 * @license         http://piengine.org/license.txt BSD 3-Clause License
 * @package         Service
 */

namespace Pi\Application\Service;

use Laminas\Captcha\AdapterInterface;

/**
 * CAPTCHA service
 *
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
class Captcha extends AbstractService
{
    /** {@inheritDoc} */
    protected $fileIdentifier = 'captcha';

    /**
     * Load CAPTCHA adapter
     *
     * @param string $type
     * @param array $options
     *
     * @return AdapterInterface
     */
    public function load($type = null, $options = [])
    {
        $type  = $type ?: 'image';
        $class = 'Pi\Captcha\\' . ucfirst($type);
        if (!class_exists($class)) {
            $class = 'Laminas\Captcha\\' . ucfirst($type);
        }
        if ($options) {
            $options = array_merge($this->options, $options);
        } else {
            $options = $this->options;
        }
        $captcha = new $class($options);

        return $captcha;
    }
}
