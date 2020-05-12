<?php
/**
 * Pi Engine (http://piengine.org)
 *
 * @link            http://code.piengine.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://piengine.org
 * @license         http://piengine.org/license.txt BSD 3-Clause License
 */

namespace Module\Demo\Validator;

use Pi;
use Laminas\Validator\AbstractValidator;

class RouteNameDuplicate extends AbstractValidator
{
    const TAKEN = 'routeExists';

    /**
     * @var array
     */
    protected $messageTemplates
        = [
            self::TAKEN => 'Route name already exists',
        ];

    /**
     * Route name validate
     *
     * @param  mixed $value
     * @param  array $context
     * @return boolean
     */
    public function isValid($value, $context = null)
    {
        $this->setValue($value);

        if (null !== $value) {
            $where = ['name' => $value];
            if (!empty($context['id'])) {
                $where['id <> ?'] = $context['id'];
            }
            $rowset = Pi::model('route')->select($where);
            if ($rowset->count()) {
                $this->error(static::TAKEN);
                return false;
            }
        }

        return true;
    }
}
