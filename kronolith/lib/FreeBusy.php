<?php
/**
 * Free/Busy functionality.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @package Kronolith
 */
class Kronolith_FreeBusy {
    /**
     * Generates the free/busy text for $calendar. Cache it for at least an
     * hour, as well.
     *
     * @param string|array $calendar  The calendar to view free/busy slots for.
     * @param integer $startstamp     The start of the time period to retrieve.
     * @param integer $endstamp       The end of the time period to retrieve.
     * @param boolean $returnObj      Default false. Return a vFreebusy object
     *                                instead of text.
     * @param string $user            Set organizer to this user.
     *
     * @return string  The free/busy text.
     */
    function generate($calendar, $startstamp = null, $endstamp = null,
                      $returnObj = false, $user = null)
    {
        global $kronolith_shares;

        if (!is_array($calendar)) {
            $calendar = array($calendar);
        }

        /* Fetch the appropriate share and check permissions. */
        $share = &$kronolith_shares->getShare($calendar[0]);
        if (is_a($share, 'PEAR_Error')) {
            // Might be a Kronolith_Resource
            try {
                $resource = Kronolith_Resource::isResourceCalendar($calendar[0]);
                $owner = $calendar[0];
            } catch (Horde_Exception $e) {
                return $returnObj ? $share : '';
            }
        } else {
            $owner = $share->get('owner');
        }

        /* Default the start date to today. */
        if (is_null($startstamp)) {
            $startstamp = mktime(0, 0, 0);
        }

        /* Default the end date to the start date + freebusy_days. */
        if (is_null($endstamp) || $endstamp < $startstamp) {
            $enddate = new Horde_Date($startstamp);
            $enddate->mday += $GLOBALS['prefs']->getValue('freebusy_days');
            $endstamp = $enddate->timestamp();
        } else {
            $enddate = new Horde_Date($endstamp);
        }

        /* Get the Identity for the owner of the share. */
        $identity = Horde_Prefs_Identity::singleton('none', $user ? $user : $owner);
        $email = $identity->getValue('from_addr');
        $cn = $identity->getValue('fullname');

        /* Fetch events. */
        $busy = Kronolith::listEvents(new Horde_Date($startstamp), $enddate, $calendar);
        if (is_a($busy, 'PEAR_Error')) {
            return $busy;
        }

        /* Create the new iCalendar. */
        $vCal = new Horde_iCalendar();
        $vCal->setAttribute('PRODID', '-//The Horde Project//Kronolith ' . $GLOBALS['registry']->getVersion() . '//EN');
        $vCal->setAttribute('METHOD', 'PUBLISH');

        /* Create new vFreebusy. */
        $vFb = &Horde_iCalendar::newComponent('vfreebusy', $vCal);
        $params = array();
        if (!empty($cn)) {
            $params['CN'] = $cn;
        }
        if (!empty($email)) {
            $vFb->setAttribute('ORGANIZER', 'mailto:' . $email, $params);
        } else {
            $vFb->setAttribute('ORGANIZER', '', $params);
        }

        $vFb->setAttribute('DTSTAMP', $_SERVER['REQUEST_TIME']);
        $vFb->setAttribute('DTSTART', $startstamp);
        $vFb->setAttribute('DTEND', $endstamp);
        $vFb->setAttribute('URL', Horde::applicationUrl('fb.php?u=' . $owner, true, -1));

        /* Add all the busy periods. */
        foreach ($busy as $events) {
            foreach ($events as $event) {
                if ($event->hasStatus(Kronolith::STATUS_FREE)) {
                    continue;
                }
                if ($event->hasStatus(Kronolith::STATUS_CANCELLED)) {
                    continue;
                }

                /* Horde_iCalendar_vfreebusy only supports timestamps at the
                 * moment. */
                $vFb->addBusyPeriod('BUSY', $event->start->timestamp(), null,
                                    $event->end->timestamp() - $event->start->timestamp());
            }
        }

        /* Remove the overlaps. */
        $vFb->simplify();
        $vCal->addComponent($vFb);

        /* Return the vFreebusy object if requested. */
        if ($returnObj) {
            return $vFb;
        }

        /* Generate the vCal file. */
        return $vCal->exportvCalendar();
    }

    /**
     * Retrieves the free/busy information for a given email address, if any
     * information is available.
     *
     * @param string $email  The email address to look for.
     * @param boolean $json  Whether to return the free/busy data as a simple
     *                       object suitable to be transferred as json.
     *
     * @return Horde_iCalendar_vfreebusy  Free/busy component on success,
     *                                    PEAR_Error on failure.
     */
    function get($email, $json = false)
    {
        /* Properly handle RFC822-compliant email addresses. */
        static $rfc822;
        if (is_null($rfc822)) {
            $rfc822 = new Mail_RFC822();
        }

        $default_domain = empty($GLOBALS['conf']['storage']['default_domain']) ? null : $GLOBALS['conf']['storage']['default_domain'];
        $res = $rfc822->parseAddressList($email, $default_domain);
        if (is_a($res, 'PEAR_Error')) {
            return $res;
        }
        if (!count($res)) {
            return PEAR::raiseError(_("No valid email address found"));
        }

        $email = Horde_Mime_Address::writeAddress($res[0]->mailbox, $res[0]->host);

        /* Check if we can retrieve a VFB from the Free/Busy URL, if one is
         * set. */
        $url = Kronolith_FreeBusy::getUrl($email);
        if ($url) {
            $url = trim($url);
            $options['method'] = 'GET';
            $options['timeout'] = 5;
            $options['allowRedirects'] = true;

            if (!empty($GLOBALS['conf']['http']['proxy']['proxy_host'])) {
                $options = array_merge($options, $GLOBALS['conf']['http']['proxy']);
            }

            $http = new HTTP_Request($url, $options);
            if (is_a($response = @$http->sendRequest(), 'PEAR_Error')) {
                return PEAR::raiseError(sprintf(_("The free/busy url for %s cannot be retrieved."), $email));
            }
            if ($http->getResponseCode() == 200 &&
                $data = $http->getResponseBody()) {
                // Detect the charset of the iCalendar data.
                $contentType = $http->getResponseHeader('Content-Type');
                if ($contentType && strpos($contentType, ';') !== false) {
                    list(,$charset,) = explode(';', $contentType);
                    $charset = trim(str_replace('charset=', '', $charset));
                } else {
                    $charset = 'UTF-8';
                }

                $vCal = new Horde_iCalendar();
                $vCal->parsevCalendar($data, 'VCALENDAR', $charset);
                $components = $vCal->getComponents();

                $vCal = new Horde_iCalendar();
                $vFb = Horde_iCalendar::newComponent('vfreebusy', $vCal);
                $vFb->setAttribute('ORGANIZER', $email);
                $found = false;
                foreach ($components as $component) {
                    if (is_a($component, 'Horde_iCalendar_vfreebusy')) {
                        $found = true;
                        $vFb->merge($component);
                    }
                }

                if ($found) {
                    return $json ? Kronolith_FreeBusy::toJson($vFb) : $vFb;
                }
            }
        }

        /* Check storage driver. */
        $storage = Kronolith_Storage::singleton();

        $fb = $storage->search($email);
        if (!is_a($fb, 'PEAR_Error')) {
            return $json ? Kronolith_FreeBusy::toJson($fb) : $fb;
        } elseif ($fb->getCode() == Kronolith::ERROR_FB_NOT_FOUND) {
            return $url ?
                PEAR::raiseError(sprintf(_("No free/busy information found at the free/busy url of %s."), $email)) :
                PEAR::raiseError(sprintf(_("No free/busy url found for %s."), $email));
        }

        /* Or else return an empty VFB object. */
        $vCal = new Horde_iCalendar();
        $vFb = Horde_iCalendar::newComponent('vfreebusy', $vCal);
        $vFb->setAttribute('ORGANIZER', $email);

        return $json ? Kronolith_FreeBusy::toJson($vFb) : $vFb;
    }

    /**
     * Searches address books for the freebusy URL for a given email address.
     *
     * @param string $email  The email address to look for.
     *
     * @return mixed  The url on success or false on failure.
     */
    function getUrl($email)
    {
        $sources = $GLOBALS['prefs']->getValue('search_sources');
        $sources = empty($sources) ? array() : explode("\t", $sources);

        try {
            $result = $GLOBALS['registry']->call('contacts/getField',
                                                 array($email, 'freebusyUrl', $sources, true, true));
        } catch (Horde_Exception $e) {
            return false;
        }
        if (is_array($result)) {
            return array_shift($result);
        }

        return $result;
    }

    /**
     * Converts free/busy data to a simple object suitable to be transferred
     * as json.
     *
     * @param Horde_iCalendar_vfreebusy $fb  A Free/busy component.
     *
     * @return object  A simple object representation.
     */
    function toJson($fb)
    {
        $json = new stdClass;
        $json->e = $fb->getEmail();
        $start = $fb->getStart();
        if ($start) {
            $start = new Horde_Date($start);
            $json->s = $start->dateString();
        }
        $end = $fb->getStart();
        if ($end) {
            $end = new Horde_Date($end);
            $json->e = $end->dateString();
        }
        $json->b = $fb->getBusyPeriods();
        return $json;
    }

}
