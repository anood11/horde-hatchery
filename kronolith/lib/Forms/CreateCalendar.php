<?php
/**
 * Horde_Form for creating calendars.
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
 * The Kronolith_CreateCalendarForm class provides the form for
 * creating a calendar.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @package Kronolith
 */
class Kronolith_CreateCalendarForm extends Horde_Form {

    function Kronolith_CreateCalendarForm(&$vars)
    {
        parent::Horde_Form($vars, _("Create Calendar"));

        $this->addVariable(_("Name"), 'name', 'text', true);
        $this->addVariable(_("Color"), 'color', 'colorpicker', false);
        $this->addVariable(_("Description"), 'description', 'longtext', false, false, null, array(4, 60));
        $this->addVariable(_("Tags"), 'tags', 'text', false);
        $this->setButtons(array(_("Create")));
    }

    function execute()
    {
        // Create new share.
        $calendar = $GLOBALS['kronolith_shares']->newShare(hash('md5', microtime()));
        if (is_a($calendar, 'PEAR_Error')) {
            return $calendar;
        }
        $calendar->set('name', $this->_vars->get('name'));
        $calendar->set('color', $this->_vars->get('color'));
        $calendar->set('desc', $this->_vars->get('description'));
        $tagger = Kronolith::getTagger();

        $tagger->tag($calendar->getName(), $this->_vars->get('tags'), 'calendar');
        return $GLOBALS['kronolith_shares']->addShare($calendar);
    }

}
