<?php
/**
 * Koowa Framework - http://developer.joomlatools.com/koowa
 *
 * @copyright	Copyright (C) 2007 - 2013 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link		http://github.com/joomlatools/koowa for the canonical source repository
 */
/**
 * Abstract Controller Behavior
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\Controller
 */
abstract class KControllerBehaviorAbstract extends KBehaviorAbstract
{
    /**
     * Get the methods that are available for mixin based
     *
     *  This function also dynamically adds a function of format _action[Action]
     *
     * @param KObjectMixable $mixer     The mixer requesting the mixable methods.
     * @param  array         $exclude   A list of methods to exclude
     * @return array An array of methods
     */
    public function getMixableMethods(KObjectMixable $mixer = null, $exclude = array())
    {
        $methods = parent::getMixableMethods($mixer, $exclude);

        foreach($this->getMethods() as $method)
        {
            if(substr($method, 0, 7) == '_action') {
                $methods[strtolower(substr($method, 7))] = strtolower(substr($method, 7));
            }
        }

        return $methods;
    }
}
