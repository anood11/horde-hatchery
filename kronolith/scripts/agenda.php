#!/usr/bin/env php
<?php
/**
 * Copyright 2003-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author Chuck Hagenbuch <chuck@horde.org>
 */

$kronolith_authentication = 'none';
require_once dirname(__FILE__) . '/../lib/base.php';

// Make sure no one runs this from the web.
if (!Horde_Cli::runningFromCLI()) {
    exit("Must be run from the command line\n");
}

// Load the CLI environment - make sure there's no time limit, init
// some variables, etc.
Horde_Cli::init();

send_agendas();
exit(0);


/**
 */
function send_agendas()
{
    if (isset($_SERVER['REQUEST_TIME'])) {
        $runtime = $_SERVER['REQUEST_TIME'];
    } else {
        $runtime = time();
    }

    $calendars = $GLOBALS['shares']->listAllShares();

    // If there are no calendars to check, we're done.
    if (!count($calendars)) {
        return;
    }

    if (!empty($GLOBALS['conf']['reminder']['server_name'])) {
        $GLOBALS['conf']['server']['name'] = $GLOBALS['conf']['reminder']['server_name'];
    }

    // Retrieve a list of users associated with each calendar, and
    // thus a list of users who have used kronolith and
    // potentially have an agenda preference set.
    $users = array();
    foreach (array_keys($calendars) as $calendarId) {
        $calendar = $GLOBALS['shares']->getShare($calendarId);
        if (is_a($calendar, 'PEAR_Error')) {
            continue;
        }
        $users = array_merge($users, $calendar->listUsers(Horde_Perms::READ));
    }

    // Remove duplicates.
    $users = array_unique($users);

    $runtime = new Horde_Date($runtime);
    $default_timezone = date_default_timezone_get();
    $kronolith_driver = Kronolith::getDriver();

    // Loop through the users and generate an agenda for them
    foreach ($users as $user) {
        $prefs = Horde_Prefs::singleton($GLOBALS['conf']['prefs']['driver'],
                                        'kronolith', $user);
        $prefs->retrieve();
        $agenda_calendars = $prefs->getValue('daily_agenda');

        // Check if user has a timezone pref, and set it. Otherwise, make
        // sure to use the server's default timezone.
        $tz = $prefs->getValue('timezone');
        date_default_timezone_set(empty($tz) ? $default_timezone : $tz);

        if (!$agenda_calendars) {
            continue;
        }

        // try to find an email address for the user
        $identity = Horde_Prefs_Identity::singleton('none', $user);
        $email = $identity->getValue('from_addr');
        if (strstr($email, '@')) {
            list($mailbox, $host) = explode('@', $email);
            $email = Horde_Mime_Address::writeAddress($mailbox, $host, $identity->getValue('fullname'));
        }

        if (empty($email)) {
            continue;
        }

        // If we found an email address, generate the agenda.
        switch ($agenda_calendars) {
        case 'owner':
            $calendars = $GLOBALS['shares']->listShares($user, Horde_Perms::SHOW, $user);
            break;

        case 'read':
            $calendars = $GLOBALS['shares']->listShares($user, Horde_Perms::SHOW, null);
            break;

        case 'show':
        default:
            $calendars = array();
            $shown_calendars = unserialize($prefs->getValue('display_cals'));
            $cals = $GLOBALS['shares']->listShares($user, Horde_Perms::SHOW, null);
            foreach ($cals as $calId => $cal) {
                if (in_array($calId, $shown_calendars)) {
                    $calendars[$calId] = $cal;
                }
            }
        }

        // Get a list of events for today
        $eventlist = array();
        foreach ($calendars as $calId => $calendar) {
            $kronolith_driver->open($calId);
            $events = $kronolith_driver->listEvents($runtime, $runtime);
            foreach ($events as $dayevents) {
                foreach ($dayevents as $event) {
                    // The event list contains events starting at 12am.
                    if ($event->start->mday != $runtime->mday) {
                        continue;
                    }
                    $eventlist[$event->start->strftime('%Y%m%d%H%M%S')] = $event;
                }
            }
        }

        if (!count($eventlist)) {
            continue;
        }

        // If there are any events, generate and send the email.
        ksort($eventlist);
        $lang = $prefs->getValue('language');
        $twentyFour = $prefs->getValue('twentyFour');
        $dateFormat = $prefs->getValue('date_format');
        Horde_Nls::setLanguageEnvironment($lang);
        $mime_mail = new Horde_Mime_Mail(array('subject' => sprintf(_("Your daily agenda for %s"), strftime($dateFormat, $runtime)),
                                               'to' => $email,
                                               'from' => $GLOBALS['conf']['reminder']['from_addr'],
                                               'charset' => Horde_Nls::getCharset()));
        $mime_mail->addHeader('User-Agent', 'Kronolith ' . $registry->getVersion());

        $pad = max(Horde_String::length(_("All day")) + 2, $twentyFour ? 6 : 8);

        $message = sprintf(_("Your daily agenda for %s"),
                           strftime($dateFormat, $runtime))
            . "\n\n";
        foreach ($eventlist as $event) {
            if ($event->isAllDay()) {
                $message .= str_pad(_("All day") . ':', $pad);
            } else {
                $message .= str_pad($event->start->format($twentyFour  ? 'H:i' : 'h:ia'), $pad);
            }
            $message .= $event->title . "\n";
        }

        $mime_mail->setBody($message, Horde_Nls::getCharset(), true);
        try {
            $mime_mail->addRecipients($email);
        } catch (Horde_Mime_Exception $e) {}
        Horde::logMessage(sprintf('Sending daily agenda to %s', $email),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);
        try {
            $mime_mail->send(Horde::getMailerConfig(), false, false);
        } catch (Horde_Mime_Exception $e) {}
    }
}
