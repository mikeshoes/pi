<?php
/**
 * Pi Engine (http://piengine.org)
 *
 * @link            http://code.piengine.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://piengine.org
 * @license         http://piengine.org/license.txt BSD 3-Clause License
 */

namespace Pi\Mvc\Service;

use Pi;
use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

/**
 * Translator service factory
 *
 * Overrides Zend translator factory in order to keep a consistent i18n service
 *
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
class TranslatorServiceFactory implements FactoryInterface
{
    /**
     * Create the translator service
     *
     * Return `Pi::service('i18n')->getTranslator()` directly
     * as the consistent translator API collection
     *
     * @param  ServiceLocatorInterface $serviceLocator
     * @return Pi\I18n\Translator
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        return Pi::service('i18n')->getTranslator();
    }
}
