<?php
/**
 * Horde_Form for subscribing to remote calendars.
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @package Kronolith
 */

/** Horde_Form */
require_once 'Horde/Form.php';

/** Horde_Form_Renderer */
require_once 'Horde/Form/Renderer.php';

/**
 * The Kronolith_SubscribeRemoteCalendarForm class provides the form
 * for subscribing to remote calendars
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @package Kronolith
 */
class Kronolith_SubscribeRemoteCalendarForm extends Horde_Form {

    function Kronolith_SubscribeRemoteCalendarForm(&$vars)
    {
        parent::Horde_Form($vars, _("Subscribe to a Remote Calendar"));

        $this->addVariable(_("Name"), 'name', 'text', true);
        $this->addVariable(_("Color"), 'color', 'colorpicker', false);
        $this->addVariable(_("URL"), 'url', 'text', true);
        $this->addVariable(_("Description"), 'description', 'longtext', false, false, null, array(4, 60));
        $this->addVariable(_("Username"), 'username', 'text', false);
        $this->addVariable(_("Password"), 'password', 'password', false);

        $this->setButtons(array(_("Subscribe")));
    }

    function execute()
    {
        $info = array();
        foreach (array('name', 'url', 'color', 'username', 'password') as $key) {
            $info[$key] = $this->_vars->get($key);
        }
        return Kronolith::subscribeRemoteCalendar($info);
    }

}
