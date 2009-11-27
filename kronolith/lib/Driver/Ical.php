<?php
/**
 * The Kronolith_Driver_Ical class implements the Kronolith_Driver API for
 * iCalendar data.
 *
 * Possible driver parameters:
 * - url:      The location of the remote calendar.
 * - proxy:    A hash with HTTP proxy information.
 * - user:     The user name for HTTP Basic Authentication.
 * - password: The password for HTTP Basic Authentication.
 *
 * Copyright 2004-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @todo Replace session cache
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jan Schneider <jan@horde.org>
 * @package Kronolith
 */
class Kronolith_Driver_Ical extends Kronolith_Driver
{
    /**
     * Cache events as we fetch them to avoid fetching or parsing the same
     * event twice.
     *
     * @var array
     */
    private $_cache = array();

    public function listAlarms($date, $fullevent = false)
    {
        return array();
    }

    /**
     * Lists all events in the time range, optionally restricting results to
     * only events with alarms.
     *
     * @param Horde_Date $startInterval  Start of range date object.
     * @param Horde_Date $endInterval    End of range data object.
     * @param boolean $showRecurrence    Return every instance of a recurring
     *                                   event? If false, will only return
     *                                   recurring events once inside the
     *                                   $startDate - $endDate range.
     * @param boolean $hasAlarm          Only return events with alarms?
     * @param boolean $json              Store the results of the events'
     *                                   toJson() method?
     *
     * @return array  Events in the given time range.
     */
    public function listEvents($startDate = null, $endDate = null,
                               $showRecurrence = false, $hasAlarm = false,
                               $json = false)
    {
        $iCal = $this->_getRemoteCalendar();
        if (is_a($iCal, 'PEAR_Error')) {
            return $iCal;
        }

        if (is_null($startDate)) {
            $startDate = new Horde_Date(array('mday' => 1,
                                              'month' => 1,
                                              'year' => 0000));
        }
        if (is_null($endDate)) {
            $endDate = new Horde_Date(array('mday' => 31,
                                            'month' => 12,
                                            'year' => 9999));
        }

        $startDate = clone $startDate;
        $startDate->hour = $startDate->min = $startDate->sec = 0;
        $endDate = clone $endDate;
        $endDate->hour = 23;
        $endDate->min = $endDate->sec = 59;

        $components = $iCal->getComponents();
        $events = array();
        $count = count($components);
        $exceptions = array();
        for ($i = 0; $i < $count; $i++) {
            $component = $components[$i];
            if ($component->getType() == 'vEvent') {
                $event = new Kronolith_Event_Ical($this);
                $event->status = Kronolith::STATUS_FREE;
                $event->fromiCalendar($component);
                $event->remoteCal = $this->_calendar;
                // Force string so JSON encoding is consistent across drivers.
                $event->eventID = 'ical' . $i;

                /* Catch RECURRENCE-ID attributes which mark single recurrence
                 * instances. */
                $recurrence_id = $component->getAttribute('RECURRENCE-ID');
                if (is_int($recurrence_id) &&
                    is_string($uid = $component->getAttribute('UID')) &&
                    is_int($seq = $component->getAttribute('SEQUENCE'))) {
                    $exceptions[$uid][$seq] = $recurrence_id;
                }

                /* Ignore events out of the period. */
                if (
                    /* Starts after the period. */
                    $event->start->compareDateTime($endDate) > 0 ||
                    /* End before the period and doesn't recur. */
                    (!$event->recurs() &&
                     $event->end->compareDateTime($startDate) < 0) ||
                    /* Recurs and ... */
                    ($event->recurs() &&
                      /* ... has a recurrence end before the period. */
                      ($event->recurrence->hasRecurEnd() &&
                       $event->recurrence->recurEnd->compareDateTime($startDate) < 0))) {
                    continue;
                }

                $events[] = $event;
            }
        }

        /* Loop through all explicitly defined recurrence intances and create
         * exceptions for those in the event with the matchin recurrence. */
        $results = array();
        foreach ($events as $key => $event) {
            if ($event->recurs() &&
                isset($exceptions[$event->getUID()][$event->getSequence()])) {
                $timestamp = $exceptions[$event->getUID()][$event->getSequence()];
                $events[$key]->recurrence->addException(date('Y', $timestamp), date('m', $timestamp), date('d', $timestamp));
            }
            Kronolith::addEvents($results, $event, $startDate, $endDate,
                                 $showRecurrence, $json);
        }

        return $results;
    }

    public function getEvent($eventId = null)
    {
        if (!$eventId) {
            return new Kronolith_Event_Ical($this);
        }
        $eventId = str_replace('ical', '', $eventId);
        $iCal = $this->_getRemoteCalendar();
        if (is_a($iCal, 'PEAR_Error')) {
            return $iCal;
        }

        $components = $iCal->getComponents();
        if (isset($components[$eventId]) &&
            $components[$eventId]->getType() == 'vEvent') {
            $event = new Kronolith_Event_Ical($this);
            $event->status = Kronolith::STATUS_FREE;
            $event->fromiCalendar($components[$eventId]);
            $event->remoteCal = $this->_calendar;
            $event->eventID = $eventId;

            return $event;
        }

        return false;
    }

    /**
     * Fetches a remote calendar into the session and return the data.
     *
     * @return Horde_iCalendar  The calendar data, or an error on failure.
     */
    private function _getRemoteCalendar()
    {
        $url = trim($this->_calendar);

        /* Treat webcal:// URLs as http://. */
        if (substr($url, 0, 9) == 'webcal://') {
            $url = str_replace('webcal://', 'http://', $url);
        }

        if (!empty($_SESSION['kronolith']['remote'][$url])) {
            return $_SESSION['kronolith']['remote'][$url];
        }

        $options['method'] = 'GET';
        $options['timeout'] = isset($this->_params['timeout'])
            ? $this->_params['timeout']
            : 5;
        $options['allowRedirects'] = true;

        if (isset($this->_params['proxy'])) {
            $options = array_merge($options, $this->_params['proxy']);
        }

        $http = new HTTP_Request($url, $options);
        if (!empty($this->_params['user'])) {
            $http->setBasicAuth($this->_params['user'],
                                $this->_params['password']);
        }
        @$http->sendRequest();
        if (!$http->getResponseCode()) {
            Horde::logMessage(sprintf('Timed out retrieving remote calendar: url = "%s"',
                                      $url),
                              __FILE__, __LINE__, PEAR_LOG_INFO);
            $_SESSION['kronolith']['remote'][$url] = PEAR::raiseError(sprintf(_("Could not open %s."), $url));
            return $_SESSION['kronolith']['remote'][$url];
        }
        if ($http->getResponseCode() != 200) {
            Horde::logMessage(sprintf('Failed to retrieve remote calendar: url = "%s", status = %s',
                                      $url, $http->getResponseCode()),
                              __FILE__, __LINE__, PEAR_LOG_INFO);
            $_SESSION['kronolith']['remote'][$url] = PEAR::raiseError(sprintf(_("Could not open %s."), $url));
            return $_SESSION['kronolith']['remote'][$url];
        }

        /* Log fetch at DEBUG level. */
        Horde::logMessage(sprintf('Retrieved remote calendar for %s: url = "%s"',
                                  Horde_Auth::getAuth(), $url),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        $data = $http->getResponseBody();
        $_SESSION['kronolith']['remote'][$url] = new Horde_iCalendar();
        $result = $_SESSION['kronolith']['remote'][$url]->parsevCalendar($data);
        if (is_a($result, 'PEAR_Error')) {
            $_SESSION['kronolith']['remote'][$url] = $result;
        }

        return $_SESSION['kronolith']['remote'][$url];
    }

}
