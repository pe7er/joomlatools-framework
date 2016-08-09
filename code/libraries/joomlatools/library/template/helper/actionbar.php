<?php
/**
 * Nooku Framework - http://nooku.org/framework
 *
 * @copyright   Copyright (C) 2007 - 2014 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/nooku/nooku-framework for the canonical source repository
 */

/**
 * Action bar Template Helper
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\Template\Helper
 */
class KTemplateHelperActionbar extends KTemplateHelperAbstract
{
    /**
     * Render the action bar commands
     *
     * @param   array   $config An optional array with configuration options
     * @return  string  Html
     */
    public function render($config = array())
    {
        $config = new KObjectConfigJson($config);
        $config->append(array(
            'toolbar' => null,
            'title'   => null,
        ))->append(array(
            'icon' => $config->toolbar->getName()
        ));


        //Set a custom title
        if($config->title || $config->icon)
        {
            if($config->toolbar->hasCommand('title'))
            {
                $command = $config->toolbar->getCommand('title');

                if ($config->title) {
                    $command->set('title', $config->title);
                }

                if ($config->icon) {
                    $command->set('icon', $config->icon);
                }
            }
            else $config->toolbar->addTitle($config->title, $config->icon);
        }

        //Render the buttons
        $html = '';

        foreach ($config->toolbar->getCommands() as $command)
        {
            $name = $command->getName();

            if (($name === 'title' && $config->{'no-title'} === '') || ($name !== 'title' && $config->{'no-buttons'} === '')) {
                continue;
            }

            if(method_exists($this, $name)) {
                $html .= $this->$name(array('command' => $command));
            } else {
                $html .= $this->command(array('command' => $command));
            }
        }

        return $html;
    }

    /**
     * Render a action bar command
     *
     * @param   array|KObjectConfig   $config An optional array with configuration options
     * @return  string  Html
     */
    public function command($config = array())
    {
        $config = new KObjectConfigJson($config);
        $config->append(array(
            'command' => NULL
        ));

        $translator = $this->getObject('translator');
        $command    = $config->command;

        if ($command->allowed === false)
        {
            $command->attribs->title = $translator->translate('You are not allowed to perform this action');
            $command->attribs->class->append(array('k-is-disabled', 'k-is-unauthorized'));
        }

         //Add a toolbar class	
        $command->attribs->class->append(array('toolbar'));

        //Create the href
        $command->attribs->append(array('href' => '#'));
        if(!empty($command->href)) {
            $command->attribs['href'] = $this->getTemplate()->route($command->href);
        }

        //Create the id
        $command->attribs->id = 'toolbar-'.$command->id;

        $command->attribs->class->append(array('k-button', 'k-button--default', 'k-button-'.$command->id));

        $icon = $this-> _getIconClass($command->icon);
        if ($command->id === 'new' || $command->id === 'apply') {
            $command->attribs->class->append(array('k-button--success'));
        }

        $attribs = clone $command->attribs;
        $attribs->class = implode(" ", KObjectConfig::unbox($attribs->class));

        $html = '<a '.$this->buildAttributes($attribs).'>';

        if ($this->_useIcons()) {
            $html .= '<span class="'.$icon.'" aria-hidden="true"></span> ';
        }

        $html .= $translator->translate($command->label);
        $html .= '</a>';

        return $html;
    }

    /**
     * Render the action bar title
     *
     * @param   array   $config An optional array with configuration options
     * @return  string  Html
     */
    public function title($config = array())
    {
        $config = new KObjectConfigJson($config);
        $config->append(array(
            'command' => NULL,
        ));

        $title = $this->getObject('translator')->translate($config->command->title);
        $icon  = $config->command->icon;
        $html  = '';

        if (!empty($title))
        {
            // Strip the extension.
            $icons = explode(' ', $icon);
            foreach ($icons as &$icon) {
                $icon = 'pagetitle--' . preg_replace('#\.[^.]*$#', '', $icon);
            }

            $html = '<div class="pagetitle ' . htmlspecialchars(implode(' ', $icons)) . '"><h2>' . $title . '</h2></div>';
        }

        return $html;
    }

    /**
     * Render a separator
     *
     * @param   array   $config An optional array with configuration options
     * @return  string  Html
     */
    public function separator($config = array())
    {
        $config = new KObjectConfigJson($config);
        $config->append(array(
            'command' => NULL
        ));

        return '';
    }

    /**
     * Render a modal button
     *
     * @param   array   $config An optional array with configuration options
     * @return  string  Html
     */
    public function dialog($config = array())
    {
        $config = new KObjectConfigJson($config);
        $config->append(array(
            'command' => NULL
        ));

        $html = $this->command($config);

        return $html;
    }

    /**
     * Decides if Bootstrap buttons should use icons
     *
     * @return bool
     */
    protected function _useIcons()
    {
        return true;
    }

    /**
     * Allows to map the icon classes to different ones
     *
     * @param  string $icon Action bar icon
     * @return string Icon class
     */
    protected function _getIconClass($icon)
    {
        return $icon;
    }
}
