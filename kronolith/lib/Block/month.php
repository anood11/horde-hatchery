<?php

$block_name = _("This Month");

/**
 * Horde_Block_Kronolith_month:: Implementation of the Horde_Block API
 * to display a mini month view of calendar items.
 *
 * @package Horde_Block
 */
class Horde_Block_Kronolith_month extends Horde_Block {

    var $_app = 'kronolith';
    var $_share = null;

    function _params()
    {
        require_once dirname(__FILE__) . '/../base.php';

        $params = array('calendar' => array('name' => _("Calendar"),
                                            'type' => 'enum',
                                            'default' => '__all'));
        $params['calendar']['values']['__all'] = _("All Visible");
        foreach (Kronolith::listCalendars() as $id => $cal) {
            $params['calendar']['values'][$id] = $cal->get('name');
        }

        return $params;
    }

    /**
     * The title to go in this block.
     *
     * @return string   The title text.
     */
    function _title()
    {
        global $registry;
        require_once dirname(__FILE__) . '/../base.php';

        $title = _("All Calendars");
        $url_params = array();
        if (isset($this->_params['calendar']) && $this->_params['calendar'] != '__all') {
            $this->_share = &$GLOBALS['kronolith_shares']->getShare($this->_params['calendar']);
            if (!is_a($this->_share, 'PEAR_Error')) {
                $url_params = array('display_cal' => $this->_params['calendar']);
                $title = htmlspecialchars($this->_share->get('name'));
            }
        }
        $date = new Horde_Date(time());

        return $title . ', ' . Horde::link(Horde::url(Horde_Util::addParameter($registry->getInitialPage(), $url_params), true)) . $date->strftime('%B, %Y') . '</a>';
    }

    /**
     * The content to go in this block.
     *
     * @return string   The content
     */
    function _content()
    {
        global $prefs;

        // @TODO Remove this hack when maintenance is refactored.
        $no_maint = true;
        require_once dirname(__FILE__) . '/../base.php';

        if (isset($this->_params['calendar']) && $this->_params['calendar'] != '__all') {
            if (empty($this->_share)) {
                $this->_share = $GLOBALS['kronolith_shares']->getShare($this->_params['calendar']);
            }
            if (is_a($this->_share, 'PEAR_Error')) {
                return _(sprintf("There was an error accessing the calendar: %s", $this->_share->getMessage()));
            }
            if (is_a($this->_share, 'PEAR_Error')) {
                return $this->_share;
            }
            if (!$this->_share->hasPermission(Horde_Auth::getAuth(), Horde_Perms::SHOW)) {
                return _("Permission Denied");
            }
        }

        Horde::addScriptFile('tooltips.js', 'horde');

        $year = date('Y');
        $month = date('m');
        $startday = new Horde_Date(array('mday' => 1,
                                         'month' => $month,
                                         'year' => $year));
        $startday = $startday->dayOfWeek();
        $daysInView = Date_Calc::weeksInMonth($month, $year) * 7;
        if (!$prefs->getValue('week_start_monday')) {
            $startOfView = 1 - $startday;

            // We may need to adjust the number of days in the view if
            // we're starting weeks on Sunday.
            if ($startday == Horde_Date::DATE_SUNDAY) {
                $daysInView -= 7;
            }
            $endday = new Horde_Date(array('mday' => Horde_Date_Utils::daysInMonth($month, $year),
                                           'month' => $month,
                                           'year' => $year));
            $endday = $endday->dayOfWeek();
            if ($endday == Horde_Date::DATE_SUNDAY) {
                $daysInView += 7;
            }
        } else {
            if ($startday == Horde_Date::DATE_SUNDAY) {
                $startOfView = -5;
            } else {
                $startOfView = 2 - $startday;
            }
        }

        $startDate = new Horde_Date(array('year' => $year, 'month' => $month, 'mday' => $startOfView));
        $endDate = new Horde_Date(array('year' => $year, 'month' => $month, 'mday' => $startOfView + $daysInView,
                                        'hour' => 23, 'min' => 59, 'sec' => 59));

        /* Table start. and current month indicator. */
        $html = '<table cellspacing="1" class="block-monthgrid" width="100%"><tr>';

        /* Set up the weekdays. */
        $weekdays = array(_("Mo"), _("Tu"), _("We"), _("Th"), _("Fr"), _("Sa"));
        if (!$prefs->getValue('week_start_monday')) {
            array_unshift($weekdays, _("Su"));
        } else {
            $weekdays[] = _("Su");
        }
        foreach ($weekdays as $weekday) {
            $html .= '<th class="item">' . $weekday . '</th>';
        }

        if (isset($this->_params['calendar']) && $this->_params['calendar'] != '__all') {
            $all_events = Kronolith::listEvents(
                $startDate,
                $endDate,
                array($this->_params['calendar']), true, false, false);
        } else {
            $all_events = Kronolith::listEvents($startDate,
                                                $endDate,
                                                $GLOBALS['display_calendars']);
        }
        if (is_a($all_events, 'PEAR_Error')) {
            return '<em>' . $all_events->getMessage() . '</em>';
        }

        $weeks = array();
        $weekday = 0;
        $week = -1;
        for ($day = $startOfView; $day < $startOfView + $daysInView; ++$day) {
            if ($weekday == 7) {
                $weekday = 0;
            }
            if ($weekday == 0) {
                ++$week;
                $html .= '</tr><tr>';
            }

            $date_ob = new Kronolith_Day($month, $day, $year);
            if ($date_ob->isToday()) {
                $td_class = 'today';
            } elseif ($date_ob->month != $month) {
                $td_class = 'othermonth';
            } elseif ($date_ob->dayOfWeek() == 0 || $date_ob->dayOfWeek() == 6) {
                $td_class = 'weekend';
            } else {
                $td_class = 'text';
            }
            $html .= '<td align="center" class="' . $td_class . '">';

            $url_params = array('date' => $date_ob->dateString());
            if (isset($this->_params['calendar']) &&
                $this->_params['calendar'] != '__all') {
                $url_params['display_cal'] = $this->_params['calendar'];
            }
            /* Set up the link to the day view. */
            $url = Horde_Util::addParameter(Horde::applicationUrl('day.php', true),
                                      $url_params);

            $date_stamp = $date_ob->dateString();
            if (empty($all_events[$date_stamp])) {
                /* No events, plain link to the day. */
                $cell = Horde::linkTooltip($url, _("View Day")) . $date_ob->mday . '</a>';
            } else {
                /* There are events; create a cell with tooltip to
                 * list them. */
                $day_events = '';
                foreach ($all_events[$date_stamp] as $event) {
                    if ($event->isAllDay()) {
                        $day_events .= _("All day");
                    } else {
                        $day_events .= $event->start->strftime($prefs->getValue('twentyFour') ? '%R' : '%I:%M%p') . '-' . $event->end->strftime($prefs->getValue('twentyFour') ? '%R' : '%I:%M%p');
                    }
                    $day_events .= ':'
                        . (($event->getLocation()) ? ' (' . $event->getLocation() . ')' : '')
                        . ' ' . $event->getTitle() . "\n";
                }
                $cell = Horde::linkTooltip($url, _("View Day"), '', '', '', $day_events) . $date_ob->mday . '</a>';
            }

            /* Bold the cell if there are events. */
            if (!empty($all_events[$date_stamp])) {
                $cell = '<strong>' . $cell . '</strong>';
            }

            $html .= $cell . '</td>';
            ++$weekday;
        }

        return $html . '</tr></table>';
    }

}
