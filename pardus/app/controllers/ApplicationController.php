<?php

require_once dirname(__FILE__) . '/ApplicationController.php';

class Pardus_ApplicationController extends Horde_Controller_Base
{
    protected function _initializeApplication()
    {
        $registry = &Registry::singleton();
        $registry->pushApp('pardus', false);

        $this->webroot = $registry->get('webroot');

        global $conf;

        $this->conf = $conf['pardus'];
    }
}
