<?php
/**
 * Pi Engine (http://piengine.org)
 *
 * @link            http://code.piengine.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://piengine.org
 * @license         http://piengine.org/license.txt BSD 3-Clause License
 */

namespace Pi\Mvc\View\Http;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\Stdlib\ResponseInterface as Response;
use Laminas\View\Model\ClearableModelInterface;
use Laminas\View\Model\ViewModel;

/**
 * Erroneous strategy listener
 *
 * Prepare for error ViewModel, should be performed prior to
 *
 * - `Pi\Mvc\View\Http\ViewStrategyListener::injectTemplate()`
 *      whose priority is -89
 * - `\Laminas\Mvc\View\Http\InjectTemplateListener::injectTemplate()`
 *      whose priority is -90
 *
 * RouteNotFound is handled by:
 *  `Laminas\Mvc\View\Http\RouteNotFoundStrategy::prepareNotFoundViewModel()`
 *  whose priority is -90
 *
 * @see Pi\Mvc\View\Http\ViewStrategyListener::injectTemplate()
 * @see \Laminas\Mvc\View\Http\InjectTemplateListener::injectTemplate()
 * @see \Laminas\Mvc\View\Http\RouteNotFoundStrategy::prepareNotFoundViewModel()
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
class ErrorStrategy extends AbstractListenerAggregate
{
    /** @var  bool If event already triggered */
    protected $isTriggered = false;

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events)
    {
        $sharedEvents = $events->getSharedManager();

        $sharedEvents->attach(
            'PI_CONTROLLER',
            MvcEvent::EVENT_DISPATCH,
            [$this, 'prepareErrorViewModel'],
            -85
        );


        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_DISPATCH,
            [$this, 'prepareErrorViewModel'],
            -85
        );
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_RENDER,
            [$this, 'prepareErrorViewModel'],
            100
        );
    }

    /**
     * Create and return a view model for erroneous result
     *
     * @param  MvcEvent $e
     * @return void
     */
    public function prepareErrorViewModel(MvcEvent $e)
    {
        if ($this->isTriggered) {
            return;
        }

        // Do nothing if no error in the event
        $error = $e->getError();
        if (empty($error)) {
            return;
        }

        // Do nothing if the result is a response object
        $result = $e->getResult();
        if ($result instanceof Response) {
            return;
        }

        $response   = $e->getResponse();
        $statusCode = $response->getStatusCode();

        $templateName = '';
        switch ($statusCode) {
            case 401:
            case 403:
                $templateName = 'denied_template';
                break;
            case 404:
                // Controller route error is handled by RouteNotFoundStrategy
                $templateName = 'not_found_template';
                break;
            case 503:
            default:
                if ($statusCode >= 400) {
                    $templateName = 'error_template';
                }
                break;
        }
        if (!$templateName) {
            return;
        }

        $viewModel = null;
        if (!$result instanceof ViewModel) {
            $viewModel = new ViewModel;
        } else {
            $viewModel = $result;
        }

        //if (!$viewModel->getTemplate()) {
        $config     = $e->getApplication()->getServiceManager()->get('Config');
        $viewConfig = $config['view_manager'];
        $template   = isset($viewConfig[$templateName])
            ? $viewConfig[$templateName] : 'error';
        $viewModel->setTemplate($template);
        //}

        if (!$viewModel->getVariable('message') && is_string($error)) {
            //if (is_string($error)) {
            $viewModel->setVariable('message', $error);
        }
        $viewModel->setVariable('code', $statusCode);

        $routeMatch = $e->getRouteMatch();
        if ($routeMatch) {
            $module     = $routeMatch->getParam('module');
            $controller = $routeMatch->getParam('controller');
            $action     = $routeMatch->getParam('action');
            $viewModel->setVariable('module', $module);
            $viewModel->setVariable('controller', $controller);
            $viewModel->setVariable('action', $action);
        }

        $e->setResult($viewModel);

        // Inject error ViewModel to root ViewModel in case
        // InjectViewModelListener is not triggered
        $model = $e->getViewModel();
        if ($model instanceof ClearableModelInterface) {
            $model->clearChildren();
        }
        $model->addChild($viewModel);

        $this->isTriggered = true;
    }
}
