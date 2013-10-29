<?php
/**
 * Koowa Framework - http://developer.joomlatools.com/koowa
 *
 * @copyright	Copyright (C) 2007 - 2013 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link		http://github.com/joomlatools/koowa for the canonical source repository
 */

/**
 * Command Context
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\Command
 */
class KCommand extends KObjectConfig implements KCommandInterface
{
    /**
     * Error
     *
     * @var string
     */
    protected $_error;

    /**
     * Set the error
     *
     * @param string $error
     *
     * @return  KCommand
     */
    public function setError($error)
    {
        $this->_error = $error;
        return $this;
    }

    /**
     * Get the error
     *
     * @return  string|Exception  The error
     */
    public function getError()
    {
        return $this->_error;
    }

    /**
     * Get the command subject
     *
     * @return object	The command subject
     */
    public function getSubject()
    {
        return $this->caller;
    }

    /**
     * Set the command subject
     *
     * @param KObjectInterface $subject The command subject
     * @return KCommand
     */
    public function setSubject(KObjectInterface $subject)
    {
        $this->caller = $subject;
        return $this;
    }

    /**
     * Set a command property
     *
     * @param  string $name
     * @param  mixed  $value
     * @return void
     */
    public function set($name, $value)
    {
        if (is_array($value)) {
            $this->_data[$name] = new KObjectConfig($value);
        } else {
            $this->_data[$name] = $value;
        }
    }
}
