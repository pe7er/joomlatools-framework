<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Abstract Event Publisher
 *
 * Implementation provides a topic based event publishing mechanism. Higher priority event listeners are called first.
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\Event\Publisher
 */
abstract class KEventPublisherAbstract extends KObject implements KEventPublisherInterface
{
    /**
     * List of event listeners
     *
     * @var array
     */
    private $__listeners;

    /**
     * Enabled status of the publisher
     *
     * @var boolean
     */
    private $__enabled = true;

    /**
     * Constructor.
     *
     * @param KObjectConfig $config  An optional ObjectConfig object with configuration options
     */
    public function __construct(KObjectConfig $config)
    {
        parent::__construct($config);

        $this->__listeners = array();
        $this->__enabled   = (boolean) $config->enabled;
    }

    /**
     * Initializes the options for the object
     *
     * Called from {@link __construct()} as a first step of object instantiation.
     *
     * @param   KObjectConfig $config Configuration options
     * @return  void
     */
    protected function _initialize(KObjectConfig $config)
    {
        $config->append(array(
            'enabled' => true,
        ));

        parent::_initialize($config);
    }

    /**
     * Enable the publisher
     *
     * @return  KEventPublisherAbstract
     */
    public function enable()
    {
        $this->__enabled = true;
        return $this;
    }

    /**
     * Disable the publisher
     *
     * @return  KEventPublisherAbstract
     */
    public function disable()
    {
        $this->__enabled = false;
        return $this;
    }

    /**
     * Publish an event by calling all listeners that have registered to receive it.
     *
     * @param  string|KEventInterface             $event      The event name or a KEventInterface object
     * @param  array|Traversable|KEventInterface  $attributes An associative array, an object implementing the
     *                                                        KEventInterface or a Traversable object
     * @param  mixed                              $target     The event target
     * @throws InvalidArgumentException  If the event is not a string or does not implement the KEventInterface
     * @return null|KEventInterface Returns the event object. If the chain is not enabled will return NULL.
     */
    public function publishEvent($event, $attributes = array(), $target = null)
    {
        if ($this->isEnabled())
        {
            if(!is_string($event) && !$event instanceof KEventInterface)
            {
                throw new InvalidArgumentException(
                    'The event must be a string or implement the KEventInterface, "'.gettype($event).'" given.'
                );
            }

            //Make sure we have an event object
            if (!$event instanceof KEventInterface)
            {
                if($attributes instanceof KEventInterface)
                {
                    $name  = $event;
                    $event = $attributes;

                    $event->setName($name);
                }
                else $event = new KEvent($event, $attributes, $target);
            }

            //Instantiate the subscribers
            $this->getObject('event.subscriber.factory')->subscribeEvent($event->getName(), $this);

            //Notify the listeners
            $listeners = $this->getListeners($event->getName());

            foreach ($listeners as $listener)
            {
                call_user_func($listener, $event, $this);

                if (!$event->canPropagate()) {
                    break;
                }
            }

            return $event;
        }

        return null;
    }

    /**
     * Add an event listener
     *
     * @param string|KEventInterface  $event     The event name or a KEventInterface object
     * @param callable                $listener  The listener
     * @param integer                 $priority  The event priority, usually between 1 (high priority) and 5 (lowest),
     *                                            default is 3 (normal)
     * @throws InvalidArgumentException If the listener is not a callable
     * @throws InvalidArgumentException  If the event is not a string or does not implement the KEventInterface
     * @return KEventPublisherAbstract
     */
    public function addListener($event, $listener, $priority = KEvent::PRIORITY_NORMAL)
    {
        if (!is_callable($listener))
        {
            throw new InvalidArgumentException(
                'The listener must be a callable, "'.gettype($listener).'" given.'
            );
        }

        if (!is_string($event) && !$event instanceof KEventInterface)
        {
            throw new InvalidArgumentException(
                'The event must be a string or implement the KEventInterface, "'.gettype($event).'" given.'
            );
        }

        if($event instanceof KEventInterface) {
            $event = $event->getName();
        }

        $this->__listeners[$event][$priority][] = $listener;

        ksort($this->__listeners[$event]);
        return $this;
    }

    /**
     * Remove an event listener
     *
     * @param string|KEventInterface  $event     The event name or a KEventInterface object
     * @param callable                $listener  The listener
     * @throws InvalidArgumentException If the listener is not a callable
     * @throws InvalidArgumentException  If the event is not a string or does not implement the KEventInterface
     * @return KEventPublisherAbstract
     */
    public function removeListener($event, $listener)
    {
        if (!is_callable($listener))
        {
            throw new InvalidArgumentException(
                'The listener must be a callable, "'.gettype($listener).'" given.'
            );
        }

        if (!is_string($event) && !$event instanceof KEventInterface)
        {
            throw new InvalidArgumentException(
                'The event must be a string or implement the KEventInterface, "'.gettype($event).'" given.'
            );
        }

        if($event instanceof KEventInterface) {
            $event = $event->getName();
        }

        if (isset($this->__listeners[$event]))
        {
            foreach ($this->__listeners[$event] as $priority => $listeners)
            {
                if (false !== ($key = array_search($listener, $listeners))) {
                    unset($this->__listeners[$event][$priority][$key]);
                }
            }
        }

        return $this;
    }

    /**
     * Get a list of listeners for a specific event
     *
     * @param string|KEventInterface  $event     The event name or a KEventInterface object
     * @throws InvalidArgumentException  If the event is not a string or does not implement the KEventInterface
     * @return array An array containing the listeners ordered by priority
     */
    public function getListeners($event)
    {
        $result = array();

        if (!is_string($event) && !$event instanceof KEventInterface)
        {
            throw new InvalidArgumentException(
                'The event must be a string or implement the KEventInterface, "'.gettype($event).'" given.'
            );
        }

        if($event instanceof KEventInterface) {
            $event = $event->getName();
        }

        if (isset($this->__listeners[$event]))
        {
            foreach($this->__listeners[$event] as $priority => $listeners) {
                $result = array_merge($result, $listeners);
            }
        }

        return $result;
    }

    /**
     * Set the priority of an event
     *
     * @param  string|KEventInterface  $event     The event name or a KEventInterface object
     * @param  callable                $listener  The listener
     * @param  integer                 $priority  The event priority
     * @throws InvalidArgumentException If the listener is not a callable
     * @throws InvalidArgumentException If the event is not a string or does not implement the KEventInterface
     * @return KEventPublisherAbstract
     */
    public function setListenerPriority($event, $listener, $priority)
    {
        if (!is_callable($listener))
        {
            throw new InvalidArgumentException(
                'The listener must be a callable, "'.gettype($listener).'" given.'
            );
        }

        if (!is_string($event) && !$event instanceof KEventInterface)
        {
            throw new InvalidArgumentException(
                'The event must be a string or implement the KEventInterface, "'.gettype($event).'" given.'
            );
        }

        if($event instanceof KEventInterface) {
            $event = $event->getName();
        }

        foreach ($this->getListeners($event) as $priority => $listeners)
        {
            if (false !== ($key = array_search($listener, $listeners)))
            {
                unset($this->__listeners[$event][$priority][$key]);
                $this->__listeners[$event][$priority][] = $listener;
            }
        }

        return $this;
    }

    /**
     * Get the priority of an event
     *
     * @param string|KEventInterface  $event     The event name or a KEventInterface object
     * @param callable                $listener  The listener
     * @throws InvalidArgumentException If the listener is not a callable
     * @throws InvalidArgumentException  If the event is not a string or does not implement the KEventInterface
     * @return integer|false The event priority or FALSE if the event isn't listened for.
     */
    public function getListenerPriority($event, $listener)
    {
        $result = false;

        if (!is_callable($listener))
        {
            throw new InvalidArgumentException(
                'The listener must be a callable, "'.gettype($listener).'" given.'
            );
        }

        if (!is_string($event) && !$event instanceof KEventInterface)
        {
            throw new InvalidArgumentException(
                'The event must be a string or implement the KEventInterface, "'.gettype($event).'" given.'
            );
        }

        if($event instanceof KEventInterface) {
            $event = $event->getName();
        }

        foreach ($this->getListeners($event) as $priority => $listeners)
        {
            if (false !== ($key = array_search($listener, $listeners)))
            {
                $result = $priority;
                break;
            }
        }

        return $result;
    }

    /**
     * Check of the publisher is enabled
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->__enabled;
    }
}
