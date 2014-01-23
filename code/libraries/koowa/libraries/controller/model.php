<?php
/**
 * Koowa Framework - http://developer.joomlatools.com/koowa
 *
 * @copyright	Copyright (C) 2007 - 2013 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link		http://github.com/joomlatools/koowa for the canonical source repository
 */

/**
 * Abstract Model Controller
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\Controller
 */
abstract class KControllerModel extends KControllerView implements KControllerModellable
{
    /**
     * Model object or identifier (com://APP/COMPONENT.model.NAME)
     *
     * @var	string|object
     */
    protected $_model;

    /**
     * Constructor
     *
     * @param   KObjectConfig $config Configuration options
     */
    public function __construct(KObjectConfig $config)
    {
        parent::__construct($config);

        // Set the model identifier
        $this->_model = $config->model;
    }

    /**
     * Initializes the default configuration for the object
     *
     * Called from {@link __construct()} as a first step of object instantiation.
     *
     * @param   KObjectConfig $config Configuration options
     * @return void
     */
    protected function _initialize(KObjectConfig $config)
    {
    	$config->append(array(
            'model'	=> $this->getIdentifier()->name,
        ));

        parent::_initialize($config);
    }

    /**
     * Get the view object attached to the controller
     *
     * If the view is not an object, an object identifier or a fully qualified identifier string and the request does
     * not contain view information try to get the view from based on the model state instead. If the model is unique
     * use a singular view name, if not unique use a plural view name.
     *
     * @return	KViewInterface
     */
    public function getView()
    {
        if(!$this->_view instanceof KViewInterface)
        {
            if(!$this->_view instanceof KObjectIdentifier)
            {
                if(is_string($this->_view) && strpos($this->_view, '.') === false )
                {
                    if(!$this->getRequest()->query->has('view'))
                    {
                        $view = $this->getIdentifier()->name;

                        if($this->getModel()->getState()->isUnique()) {
                            $view = KStringInflector::singularize($view);
                        } else {
                            $view = KStringInflector::pluralize($view);
                        }
                    }
                    else $view = $this->getRequest()->query->get('view', 'cmd');
                }
                else $view = $this->_view;

                //Set the view
                $this->setView($view);
            }

            //Get the view
            $view = parent::getView();

            //Set the model in the view
            $view->setModel($this->getModel());
        }

        return parent::getView();
    }

    /**
     * Get the model object attached to the controller
     *
     * @throws	\UnexpectedValueException	If the model doesn't implement the ModelInterface
     * @return	KModelInterface
     */
    public function getModel()
    {
        if(!$this->_model instanceof KModelInterface)
        {
            //Make sure we have a model identifier
            if(!($this->_model instanceof KObjectIdentifier)) {
                $this->setModel($this->_model);
            }

            $this->_model = $this->getObject($this->_model);

            if(!$this->_model instanceof KModelInterface)
            {
                throw new UnexpectedValueException(
                    'Model: '.get_class($this->_model).' does not implement KModelInterface'
                );
            }

            //Inject the request into the model state
            $this->_model->setState($this->getRequest()->query->toArray());
        }

        return $this->_model;
    }

    /**
     * Method to set a model object attached to the controller
     *
     * @param	mixed	$model An object that implements KObjectInterface, KObjectIdentifier object
     * 					       or valid identifier string
     * @return	KControllerView
     */
    public function setModel($model)
    {
        if(!($model instanceof KModelInterface))
        {
            if(is_string($model) && strpos($model, '.') === false )
            {
                // Model names are always plural
                if(KStringInflector::isSingular($model)) {
                    $model = KStringInflector::pluralize($model);
                }

                $identifier			= $this->getIdentifier()->toArray();
                $identifier['path']	= array('model');
                $identifier['name']	= $model;

                $identifier = $this->getIdentifier($identifier);
            }
            else $identifier = $this->getIdentifier($model);

            $model = $identifier;
        }

        $this->_model = $model;

        return $this->_model;
    }

    /**
     * Get action
     *
     * This function translates a GET request into a read or browse action. If the view name is singular a read action
     * will be executed, if plural a browse action will be executed.
     *
     * @param KControllerContextInterface $context A command context object
     * @return    string|bool    The rendered output of the view or FALSE if something went wrong
     */
    protected function _actionRender(KControllerContextInterface $context)
    {
        //Check if we are reading or browsing
        $action = KStringInflector::isSingular($this->getView()->getName()) ? 'read' : 'browse';

        //Execute the action
        $this->execute($action, $context);

        return parent::_actionRender($context);
    }

	/**
	 * Generic browse action, fetches a list
	 *
	 * @param	KControllerContextInterface $context A command context object
	 * @return 	KDatabaseRowsetInterface	A rowset object containing the selected rows
	 */
	protected function _actionBrowse(KControllerContextInterface $context)
	{
		$data = $this->getModel()->getList();
		return $data;
	}

    /**
     * Generic read action, fetches an item
     *
     * @param    KControllerContextInterface $context A command context object
     * @throws KControllerExceptionNotFound
     * @return    KDatabaseRowInterface     A row object containing the selected row
     */
	protected function _actionRead(KControllerContextInterface $context)
	{
	    $data = $this->getModel()->getItem();
	    $name = ucfirst($this->getView()->getName());

		if($this->getModel()->getState()->isUnique() && $data->isNew()) {
            throw new KControllerExceptionNotFound($name.' Not Found');
		}

		return $data;
	}

    /**
     * Generic edit action, saves over an existing item
     *
     * @param	KControllerContextInterface	$context A controller context object
     * @throws  KControllerExceptionNotFound If the entity could not be found
     * @return    KDatabaseRowInterface|KDatabaseRowsetInterface A row(set) object containing the updated row(s)
     */
    protected function _actionEdit(KControllerContextInterface $context)
    {
        $entity = $this->getModel()->getData();

        if(count($entity))
        {
            $entity->setData($context->request->data->toArray());

            //Only set the reset content status if the action explicitly succeeded
            if($entity->save() === true) {
                $context->response->setStatus(self::STATUS_RESET);
            } else {
                $context->response->setStatus(self::STATUS_UNCHANGED);
            }
        }
        else throw new KControllerExceptionNotFound('Resource could not be found');

        return $entity;
    }

    /**
     * Generic add action, saves a new item
     *
     * @param	KControllerContextInterface	$context A controller context object
     * @throws  KControllerExceptionActionFailed If the delete action failed on the data entity
     * @throws  KControllerExceptionBadRequest   If the entity already exists
     * @return 	KDatabaseRowInterface   A row object containing the new data
     */
    protected function _actionAdd(KControllerContextInterface $context)
    {
        $entity = $this->getModel()->getItem();

        if($entity->isNew())
        {
            $entity->setData($context->request->data->toArray());

            //Only throw an error if the action explicitly failed.
            if($entity->save() === false)
            {
                $error = $entity->getStatusMessage();
                throw new KControllerExceptionActionFailed($error ? $error : 'Add Action Failed');
            }
            else $context->response->setStatus(self::STATUS_CREATED);
        }
        else throw new KControllerExceptionBadRequest('Resource Already Exists');

        return $entity;
    }

    /**
     * Generic delete function
     *
     * @param  KControllerContextInterface $context A controller context object
     * @throws KControllerExceptionNotFound
     * @throws KControllerExceptionActionFailed
     * @return KDatabaseRowInterface|KDatabaseRowsetInterface A row(set) object containing the deleted row(s)
     */
    protected function _actionDelete(KControllerContextInterface $context)
    {
        $entity = $this->getModel()->getData();

        if($entity instanceof KDatabaseRowsetInterface)  {
            $count = count($entity);
        } else {
            $count = (int) !$entity->isNew();;
        }

        if($count)
        {
            $entity->setData($context->request->data->toArray());

            //Only throw an error if the action explicitly failed.
            if($entity->delete() === false)
            {
                $error = $entity->getStatusMessage();
                throw new KControllerExceptionActionFailed($error ? $error : 'Delete Action Failed');
            }
            else $context->response->setStatus(self::STATUS_UNCHANGED);
        }
        else throw new KControllerExceptionNotFound('Resource Not Found');

        return $entity;
    }

    /**
     * Supports a simple form Fluent Interfaces. Allows you to set the request properties by using the request property
     * name as the method name.
     *
     * For example : $controller->limit(10)->browse();
     *
     * @param	string	$method Method name
     * @param	array	$args   Array containing all the arguments for the original call
     * @return	KControllerModel
     *
     * @see http://martinfowler.com/bliki/FluentInterface.html
     */
    public function __call($method, $args)
    {
        //Check first if we are calling a mixed in method to prevent the model being
        //loaded during object instantiation.
        if(!isset($this->_mixed_methods[$method]))
        {
            //Check for model state properties
            if(isset($this->getModel()->getState()->$method))
            {
                $this->getRequest()->query->set($method, $args[0]);
                $this->getModel()->getState()->set($method, $args[0]);

                return $this;
            }
        }

        return parent::__call($method, $args);
    }
}
