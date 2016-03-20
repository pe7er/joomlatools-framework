<?php
/**
 * Kodekit - http://timble.net/kodekit
 *
 * @copyright   Copyright (C) 2007 - 2016 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     MPL v2.0 <https://www.mozilla.org/en-US/MPL/2.0>
 * @link        https://github.com/timble/kodekit for the canonical source repository
 */

namespace Kodekit\Library;

/**
 * Json Filter
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Kodekit\Library\Filter
 */
class FilterJson extends FilterAbstract
{
    /**
     * Validate a value
     *
     * @param   mixed  $value Value to be validated
     * @return  bool    True when the variable is valid
     */
    public function validate($value)
    {
        try {
            $config = $this->getObject('object.config.factory')->fromString('json', $value);
        } catch(\RuntimeException $e) {
            $config = null;
        }

        return is_string($value) && !is_null($config);
    }

    /**
     * Sanitize a value
     *
     * @param   mixed  $value Value to be sanitized
     * @return  string
     */
    public function sanitize($value)
    {
        if(!$value instanceof ObjectConfigJson)
        {
            if(is_string($value)) {
                $value = $this->getObject('object.config.factory')->fromString('json', $value);
            } else {
                $value = $this->getObject('object.config.factory')->createFormat('json', $value);
            }
        }

        return $value;
    }
}
