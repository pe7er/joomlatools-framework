<?php
/**
 * Koowa Framework - http://developer.joomlatools.com/koowa
 *
 * @copyright	Copyright (C) 2007 - 2013 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link		http://github.com/joomlatools/koowa for the canonical source repository
 */

/**
 * Abstract Dispatcher
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\Dispatcher
 */
class KDispatcherHttp extends KDispatcherAbstract implements KObjectMultiton
{
    /**
     * The limit information
     *
     * @var	array
     */
    protected $_limit;

    /**
	 * Constructor.
	 *
	 * @param KObjectConfig $config	An optional KObjectConfig object with configuration options.
	 */
	public function __construct(KObjectConfig $config)
	{
		parent::__construct($config);

        //Set the limit
        $this->_limit = $config->limit;

        //Authenticate none safe requests
        $this->registerCallback('before.post'  , array($this, 'authenticateRequest'));
        $this->registerCallback('before.put'   , array($this, 'authenticateRequest'));
        $this->registerCallback('before.delete', array($this, 'authenticateRequest'));

        //Sign GET request with a cookie token
        $this->registerCallback('after.get' , array($this, 'signResponse'));
	}

    /**
     * Initializes the options for the object
     *
     * Called from {@link __construct()} as a first step of object instantiation.
     *
     * @param 	KObjectConfig $config An optional ObjectConfig object with configuration options.
     * @return 	void
     */
    protected function _initialize(KObjectConfig $config)
    {
    	$config->append(array(
            'behaviors'  => array('resettable'),
            'limit'      => array('default' => 100)
         ));

        parent::_initialize($config);
    }

    /**
     * Check the request token to prevent CSRF exploits
     *
     * Method will always perform a referrer check. If any of the checks fail an forbidden exception should be thrown.
     *
     * @param KDispatcherContextInterface $context A dispatcher context object
     * @throws KControllerExceptionForbidden
     * @return  boolean Returns FALSE if the check failed. Otherwise TRUE.
     */
    public function authenticateRequest(KDispatcherContextInterface $context)
    {
        //Check referrer
        if(!KRequest::referrer()) {
            throw new KControllerExceptionForbidden('Invalid Request Referrer');
        }

        return true;
    }

    /**
     * Sign the response with a token
     *
     * @param KDispatcherContextInterface $context	A dispatcher context object
     */
    public function signResponse(KDispatcherContextInterface $context)
    {
        //do nothing
    }

    /**
     * Dispatch the request
     *
     * Dispatch to a controller internally. Functions makes an internal sub-request, based on the information in
     * the request and passing along the context.
     *
     * @param KDispatcherContextInterface $context	A dispatcher context object
     * @return	mixed
     */
	protected function _actionDispatch(KDispatcherContextInterface $context)
	{
        //Execute the component method
        $method = strtolower(KRequest::method());
        $result = $this->execute($method, $context);

        return $result;
	}

    /**
     * Redirect
     *
     * Redirect to a URL externally. Method performs a 301 (permanent) redirect. Method should be used to immediately
     * redirect the dispatcher to another URL after a GET request.
     *
     * @param KDispatcherContextInterface $context A dispatcher context object
     * @return bool
     */
    protected function _actionRedirect(KDispatcherContextInterface $context)
    {
        $url = $context->param;

        //Set the redirect into the response
        $context->result = sprintf(
            '<!DOCTYPE html>
                <html>
                    <head>
                        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                        <meta http-equiv="refresh" content="1;url=%1$s" />
                        <title>Redirecting to %1$s</title>
                    </head>
                    <body>
                        Redirecting to <a href="%1$s">%1$s</a>.
                    </body>
                </html>'
            , htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
        );

        $context->status = KHttpResponse::MOVED_PERMANENTLY;
        $this->send();

        return false;
    }

    /**
     * Get method
     *
     * This function translates a GET request into a render action. If the request contains a limit the limit will
     * be set the enforced to the maximum limit. Default max limit is .
     *
     * @param KDispatcherContextInterface $context	A dispatcher context object
     * @return KDatabaseRowInterface|KDatabaseRowsetInterface A row(set) object containing the modified data
     */
    protected function _actionGet(KDispatcherContextInterface $context)
    {
        $controller = $this->getController();

        if($controller instanceof KControllerModellable)
        {
            if(!$controller->getModel()->getState()->isUnique())
            {
                $limit = abs($controller->getModel()->getState()->limit);

                //Allow a zero limit, set to default is limit is not set
                if(empty($limit) && $limit != 0) {
                    $limit = $this->_limit->default;
                }

                $controller->getModel()->getState()->limit = $limit;
            }
        }

        return $controller->execute('render', $context);
    }

    /**
     * Post method
     *
     * This function translated a POST request action into an edit or add action. If the model state is unique a edit
     * action will be executed, if not unique an add action will be executed.
     *
     * If an _action parameter exists in the request data it will be used instead. If no action can be found an bad
     * request exception will be thrown.
     *
     * @param   KDispatcherContextInterface $context	A dispatcher context object
     * @throws  KDispatcherExceptionMethodNotAllowed    The action specified in the request is not allowed for the
     *          entity identified by the Request-URI. The response MUST include an Allow header containing a list of
     *          valid actions for the requested entity.
     * @throws  KControllerExceptionBadRequest           The action could not be found based on the info in the request.
     * @return 	KDatabaseRowInterface|KDatabaseRowsetInterface	A row(set) object containing the modified data
     */
    protected function _actionPost(KDispatcherContextInterface $context)
    {
        $action     = null;
        $controller = $this->getController();

        //Get the action from the request data
        if(KRequest::has('post.action'))
        {
            $action = strtolower(KRequest::get('post.action', 'alpha'));

            if(in_array($action, array('browse', 'read', 'render'))) {
                throw new KDispatcherExceptionMethodNotAllowed('Action: '.$action.' not allowed');
            }
        }
        else
        {
            //Determine the action based on the model state
            if($controller instanceof KControllerModellable) {
                $action = $controller->getModel()->getState()->isUnique() ? 'edit' : 'add';
            }
        }

        //Throw exception if no action could be determined from the request
        if(!$action) {
            throw new KControllerExceptionBadRequest('Action not found');
        }

        //Set the data in the context
        $context->data = KRequest::get(strtolower(KRequest::method()), 'raw');
        
        return $controller->execute($action, $context);
    }

    /**
     * Put method
     *
     * This function translates a PUT request into an edit or add action. Only if the model state is unique and the item
     * exists an edit action will be executed, if the entity does not exist and the state is unique an add action will
     * be executed.
     *
     * If the entity already exists it will be completely replaced based on the data available in the request.
     *
     * @param   KDispatcherContextInterface $context	A dispatcher context object
     * @throws  KControllerExceptionBadRequest 	If the model state is not unique
     * @return 	KDatabaseRowInterface|KDatabaseRowsetInterface	    A row(set) object containing the modified data
     */
    protected function _actionPut(KDispatcherContextInterface $context)
    {
        $action     = null;
        $controller = $this->getController();

        if($controller instanceof KControllerModellable)
        {
            if($controller->getModel()->getState()->isUnique())
            {
                $action = 'add';
                $entity = $controller->getModel()->getRow();

                if(!$entity->isNew())
                {
                    //Reset the row data
                    $entity->reset();
                    $action = 'edit';
                }

                //Set the row data based on the unique state information
                $state = $controller->getModel()->getState()->getValues(true);
                $entity->setData($state);
            }
            else throw new KControllerExceptionBadRequest('Resource not found');
        }

        //Throw exception if no action could be determined from the request
        if(!$action) {
            throw new KControllerExceptionBadRequest('Resource not found');
        }

        //Set the data in the context
        $context->data = KRequest::get(strtolower(KRequest::method()), 'raw');

        return $entity = $controller->execute($action, $context);
    }

    /**
     * Delete method
     *
     * This function translates a DELETE request into a delete action.
     *
     * @param   KDispatcherContextInterface $context	A dispatcher context object
     * @return 	KDatabaseRowInterface|KDatabaseRowsetInterface	A row(set) object containing the modified data
     */
    protected function _actionDelete(KDispatcherContextInterface $context)
    {
        $controller = $this->getController();

        //Set the data in the context
        $context->data = KRequest::get(strtolower(KRequest::method()), 'raw');

        return $controller->execute('delete', $context);
    }

    /**
     * Options method
     *
     * @param   KDispatcherContextInterface $context	A dispatcher context object
     * @return  string  The allowed actions; e.g., `GET, POST [add, edit, cancel, save], PUT, DELETE`
     */
    protected function _actionOptions(KDispatcherContextInterface $context)
    {
        $methods = array();

        //Retrieve HTTP methods allowed by the dispatcher
        $actions = array_diff($this->getActions(), array('dispatch'));

        foreach($actions as $key => $action)
        {
            if($this->canExecute($action)) {
                $methods[$action] = $action;
            }
        }

        //Retrieve POST actions allowed by the controller
        if(in_array('post', $methods))
        {
            $actions = array_diff($this->getController()->getActions(), array('browse', 'read', 'render'));

            foreach($actions as $key => $action)
            {
                if(!$this->getController()->canExecute($action)) {
                    unset($actions[$key]);
                }
            }

            sort($actions);

            $methods['post'] = array_diff($actions, $methods);
        }

        //Render to string
        $result = '';
        foreach($methods as $method => $actions)
        {
            $result .= strtoupper($method). ' ';
            if(is_array($actions) && !empty($actions)) {
                $result .= '['.implode(', ', $actions).'] ';
            }
        }

        header('Allow : '.$result);
    }
}