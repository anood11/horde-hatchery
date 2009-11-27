<?php
/**
 * The IMP_Horde_Mime_Viewer_Itip class displays vCalendar/iCalendar data
 * and provides an option to import the data into a calendar source,
 * if one is available.
 *
 * Copyright 2002-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Mike Cochrane <mike@graftonhall.co.nz>
 * @package Horde_Mime
 */
class IMP_Horde_Mime_Viewer_Itip extends Horde_Mime_Viewer_Driver
{
    /**
     * Can this driver render various views?
     *
     * @var boolean
     */
    protected $_capability = array(
        'embedded' => false,
        'forceinline' => false,
        'full' => true,
        'info' => false,
        'inline' => true,
        'raw' => false
    );

    /**
     * Return the full rendered version of the Horde_Mime_Part object.
     *
     * @return array  See Horde_Mime_Viewer_Driver::render().
     */
    protected function _render()
    {
        $ret = $this->_renderInline();
        if (!empty($ret)) {
            reset($ret);
            $ret[key($ret)]['data'] = Horde_Util::bufferOutput('include', $GLOBALS['registry']->get('templates', 'horde') . '/common-header.inc') .
                $ret[key($ret)]['data'] .
                Horde_Util::bufferOutput('include', $GLOBALS['registry']->get('templates', 'horde') . '/common-footer.inc');
        }
        return $ret;
    }

    /**
     * Return the rendered inline version of the Horde_Mime_Part object.
     *
     * URL parameters used by this function:
     * <pre>
     * 'identity' - (integer) TODO
     * 'itip_action' - (array) TODO
     * </pre>
     *
     * @return array  See Horde_Mime_Viewer_Driver::render().
     */
    protected function _renderInline()
    {
        global $registry;

        $charset = Horde_Nls::getCharset();
        $data = $this->_mimepart->getContents();
        $mime_id = $this->_mimepart->getMimeId();

        // Parse the iCal file.
        $vCal = new Horde_iCalendar();
        if (!$vCal->parsevCalendar($data, 'VCALENDAR', $this->_mimepart->getCharset())) {
            return array(
                $mime_id => array(
                    'data' => '<h1>' . _("The calendar data is invalid") . '</h1>' . '<pre>' . htmlspecialchars($data) . '</pre>',
                    'status' => array(),
                    'type' => 'text/html; charset=' . $charset
                )
            );
        }

        // Check if we got vcard data with the wrong vcalendar mime type.
        $c = $vCal->getComponentClasses();
        if ((count($c) == 1) && !empty($c['horde_icalendar_vcard'])) {
            return $this->_params['contents']->renderMIMEPart($mime_id, IMP_Contents::RENDER_INLINE, array('type' => 'text/x-vcard'));
        }

        // Get the method type.
        $method = $vCal->getAttribute('METHOD');
        if ($method instanceof PEAR_Error) {
            $method = '';
        }

        // Get the iCalendar file components.
        $components = $vCal->getComponents();
        $msgs = array();

        // Handle the action requests.
        $actions = Horde_Util::getFormData('itip_action', array());
        foreach ($actions as $key => $action) {
            switch ($action) {
            case 'delete':
                // vEvent cancellation.
                if ($registry->hasMethod('calendar/delete')) {
                    $guid = $components[$key]->getAttribute('UID');
                    try {
                        $registry->call('calendar/delete', array('guid' => $guid));
                        $msgs[] = array('success', _("Event successfully deleted."));
                    } catch (Horde_Exception $e) {
                        $msgs[] = array('error', _("There was an error deleting the event:") . ' ' . $e->getMessage());
                    }
                } else {
                    $msgs[] = array('warning', _("This action is not supported."));
                }
                break;

            case 'update':
                // vEvent reply.
                if ($registry->hasMethod('calendar/updateAttendee')) {
                    try {
                        $hdrs = $this->_params['contents']->getHeaderOb();
                        $event = $registry->call('calendar/updateAttendee', array('response' => $components[$key], 'sender' => $hdrs->getValue('From')));
                        $msgs[] = array('success', _("Respondent Status Updated."));
                    } catch (Horde_Exception $e) {
                        $msgs[] = array('error', _("There was an error updating the event:") . ' ' . $e->getMessage());
                    }
                } else {
                    $msgs[] = array('warning', _("This action is not supported."));
                }
                break;

            case 'import':
            case 'accept-import':
                // vFreebusy reply.
                // vFreebusy publish.
                // vEvent request.
                // vEvent publish.
                // vTodo publish.
                // vJournal publish.
                switch ($components[$key]->getType()) {
                case 'vEvent':
                    $handled = false;
                    $guid = $components[$key]->getAttribute('UID');
                    // Check if this is an update.
                    try {
                        $registry->call('calendar/export', array($guid, 'text/calendar'));
                    } catch (Horde_Exception $e) {}

                    // Try to update in calendar.
                    if ($registry->hasMethod('calendar/replace')) {
                        try {
                            $registry->call('calendar/replace', array('uid' => $guid, 'content' => $components[$key], 'contentType' => $this->_mimepart->getType()));
                            $handled = true;
                            $url = Horde::url($registry->link('calendar/show', array('uid' => $guid)));
                            $msgs[] = array('success', _("The event was updated in your calendar.") .
                                            '&nbsp;' . Horde::link($url, _("View event"), null, '_blank') . Horde::img('mime/icalendar.png', _("View event"), null, $registry->getImageDir('horde')) . '</a>');
                        } catch (Horde_Exception $e) {
                            // Could be a missing permission.
                            $msgs[] = array('warning', _("There was an error updating the event:") . ' ' . $e->getMessage() . '. ' . _("Trying to import the event instead."));
                        }
                    }

                    if (!$handled && $registry->hasMethod('calendar/import')) {
                        // Import into calendar.
                        $handled = true;
                        try {
                            $guid = $registry->call('calendar/import', array('content' => $components[$key], 'contentType' => $this->_mimepart->getType()));
                            $url = Horde::url($registry->link('calendar/show', array('uid' => $guid)));
                            $msgs[] = array('success', _("The event was added to your calendar.") .
                                            '&nbsp;' . Horde::link($url, _("View event"), null, '_blank') . Horde::img('mime/icalendar.png', _("View event"), null, $registry->getImageDir('horde')) . '</a>');
                        } catch (Horde_Exception $e) {
                            $msgs[] = array('error', _("There was an error importing the event:") . ' ' . $e->getMessage());
                        }
                    }
                    if (!$handled) {
                        $msgs[] = array('warning', _("This action is not supported."));
                    }
                    break;

                case 'vFreebusy':
                    // Import into Kronolith.
                    if ($registry->hasMethod('calendar/import_vfreebusy')) {
                        try {
                            $registry->call('calendar/import_vfreebusy', array($components[$key]));
                            $msgs[] = array('success', _("The user's free/busy information was sucessfully stored."));
                        } catch (Horde_Exception $e) {
                            $msgs[] = array('error', _("There was an error importing user's free/busy information:") . ' ' . $e->getMessage());
                        }
                    } else {
                        $msgs[] = array('warning', _("This action is not supported."));
                    }
                    break;

                case 'vTodo':
                    // Import into Nag.
                    if ($registry->hasMethod('tasks/import')) {
                        try {
                            $guid = $registry->call('tasks/import', array($components[$key], $this->_mimepart->getType()));
                            $url = Horde::url($registry->link('tasks/show', array('uid' => $guid)));
                            $msgs[] = array('success', _("The task has been added to your tasklist.") .
                                                         '&nbsp;' . Horde::link($url, _("View task"), null, '_blank') . Horde::img('mime/icalendar.png', _("View task"), null, $registry->getImageDir('horde')) . '</a>');
                        } catch (Horde_Exception $e) {
                            $msgs[] = array('error', _("There was an error importing the task:") . ' ' . $e->getMessage());
                        }
                    } else {
                        $msgs[] = array('warning', _("This action is not supported."));
                    }
                    break;

                case 'vJournal':
                default:
                    $msgs[] = array('warning', _("This action is not yet implemented."));
                }

                if ($action != 'accept-import') {
                    break;
                }

            case 'accept':
            case 'accept-import':
            case 'deny':
            case 'tentative':
                // vEvent request.
                if (isset($components[$key]) &&
                    $components[$key]->getType() == 'vEvent') {
                    $vEvent = $components[$key];

                    // Get the organizer details.
                    $organizer = $vEvent->getAttribute('ORGANIZER');
                    if ($organizer instanceof PEAR_Error) {
                        break;
                    }
                    $organizer = parse_url($organizer);
                    $organizerEmail = $organizer['path'];
                    $organizer = $vEvent->getAttribute('ORGANIZER', true);
                    $organizerName = isset($organizer['cn']) ? $organizer['cn'] : '';

                    // Build the reply.
                    $msg_headers = new Horde_Mime_Headers();
                    $vCal = new Horde_iCalendar();
                    $vCal->setAttribute('PRODID', '-//The Horde Project//' . $msg_headers->getUserAgent() . '//EN');
                    $vCal->setAttribute('METHOD', 'REPLY');

                    $vEvent_reply = Horde_iCalendar::newComponent('vevent', $vCal);
                    $vEvent_reply->setAttribute('UID', $vEvent->getAttribute('UID'));
                    if (!($vEvent->getAttribute('SUMMARY') instanceof PEAR_Error)) {
                        $vEvent_reply->setAttribute('SUMMARY', $vEvent->getAttribute('SUMMARY'));
                    }
                    if (!($vEvent->getAttribute('DESCRIPTION') instanceof PEAR_Error)) {
                        $vEvent_reply->setAttribute('DESCRIPTION', $vEvent->getAttribute('DESCRIPTION'));
                    }
                    $dtstart = $vEvent->getAttribute('DTSTART', true);
                    $vEvent_reply->setAttribute('DTSTART', $vEvent->getAttribute('DTSTART'), array_pop($dtstart));
                    if (!($vEvent->getAttribute('DTEND') instanceof PEAR_Error)) {
                        $dtend = $vEvent->getAttribute('DTEND', true);
                        $vEvent_reply->setAttribute('DTEND', $vEvent->getAttribute('DTEND'), array_pop($dtend));
                    } else {
                        $duration = $vEvent->getAttribute('DURATION', true);
                        $vEvent_reply->setAttribute('DURATION', $vEvent->getAttribute('DURATION'), array_pop($duration));
                    }
                    if (!($vEvent->getAttribute('SEQUENCE') instanceof PEAR_Error)) {
                        $vEvent_reply->setAttribute('SEQUENCE', $vEvent->getAttribute('SEQUENCE'));
                    }
                    $vEvent_reply->setAttribute('ORGANIZER', $vEvent->getAttribute('ORGANIZER'), array_pop($organizer));

                    // Find out who we are and update status.
                    $identity = Horde_Prefs_Identity::singleton(array('imp', 'imp'));
                    $attendees = $vEvent->getAttribute('ATTENDEE');
                    if (!is_array($attendees)) {
                        $attendees = array($attendees);
                    }
                    foreach ($attendees as $attendee) {
                        $attendee = preg_replace('/mailto:/i', '', $attendee);
                        if (!is_null($id = $identity->getMatchingIdentity($attendee))) {
                            $identity->setDefault($id);
                            break;
                        }
                    }
                    $name = $email = $identity->getFromAddress();
                    $params = array();
                    $cn = $identity->getValue('fullname');
                    if (!empty($cn)) {
                        $name = $params['CN'] = $cn;
                    }

                    switch ($action) {
                    case 'accept':
                    case 'accept-import':
                        $message = sprintf(_("%s has accepted."), $name);
                        $subject = _("Accepted: ") . $vEvent->getAttribute('SUMMARY');
                        $params['PARTSTAT'] = 'ACCEPTED';
                        break;

                    case 'deny':
                        $message = sprintf(_("%s has declined."), $name);
                        $subject = _("Declined: ") . $vEvent->getAttribute('SUMMARY');
                        $params['PARTSTAT'] = 'DECLINED';
                        break;

                    case 'tentative':
                        $message = sprintf(_("%s has tentatively accepted."), $name);
                        $subject = _("Tentative: ") . $vEvent->getAttribute('SUMMARY');
                        $params['PARTSTAT'] = 'TENTATIVE';
                        break;
                    }

                    $vEvent_reply->setAttribute('ATTENDEE', 'mailto:' . $email, $params);
                    $vCal->addComponent($vEvent_reply);

                    $mime = new Horde_Mime_Part();
                    $mime->setType('multipart/alternative');

                    $body = new Horde_Mime_Part();
                    $body->setType('text/plain');
                    $body->setCharset($charset);
                    $body->setContents(Horde_String::wrap($message, 76, "\n"));

                    $ics = new Horde_Mime_Part();
                    $ics->setType('text/calendar');
                    $ics->setCharset($charset);
                    $ics->setContents($vCal->exportvCalendar());
                    $ics->setName('event-reply.ics');
                    $ics->setContentTypeParameter('METHOD', 'REPLY');

                    $mime->addPart($body);
                    $mime->addPart($ics);

                    // Build the reply headers.
                    $msg_headers->addReceivedHeader();
                    $msg_headers->addMessageIdHeader();
                    $msg_headers->addHeader('Date', date('r'));
                    $msg_headers->addHeader('From', $email);
                    $msg_headers->addHeader('To', $organizerEmail);

                    $identity->setDefault(Horde_Util::getFormData('identity'));
                    $replyto = $identity->getValue('replyto_addr');
                    if (!empty($replyto) && ($replyto != $email)) {
                        $msg_headers->addHeader('Reply-to', $replyto);
                    }
                    $msg_headers->addHeader('Subject', Horde_Mime::encode($subject, $charset));

                    // Send the reply.
                    $mail_driver = IMP_Compose::getMailDriver();
                    try {
                        $mime->send($organizerEmail, $msg_headers,
                                    $mail_driver['driver'],
                                    $mail_driver['params']);
                        $msgs[] = array('success', _("Reply Sent."));
                    } catch (Horde_Mime_Exception $e) {
                        $msgs[] = array('error', sprintf(_("Error sending reply: %s."), $e->getMessage()));
                    }
                } else {
                    $msgs[] = array('warning', _("This action is not supported."));
                }
                break;

            case 'send':
                // vEvent refresh.
                if (isset($components[$key]) &&
                    $components[$key]->getType() == 'vEvent') {
                    $vEvent = $components[$key];
                }

                // vTodo refresh.
            case 'reply':
            case 'reply2m':
                // vfreebusy request.
                if (isset($components[$key]) &&
                    $components[$key]->getType() == 'vFreebusy') {
                    $vFb = $components[$key];

                    // Get the organizer details.
                    $organizer = $vFb->getAttribute('ORGANIZER');
                    if ($organizer instanceof PEAR_Error) {
                        break;
                    }
                    $organizer = parse_url($organizer);
                    $organizerEmail = $organizer['path'];
                    $organizer = $vFb->getAttribute('ORGANIZER', true);
                    $organizerName = isset($organizer['cn']) ? $organizer['cn'] : '';

                    if ($action == 'reply2m') {
                        $startStamp = time();
                        $endStamp = $startStamp + (60 * 24 * 3600);
                    } else {
                        $startStamp = $vFb->getAttribute('DTSTART');
                        if ($startStamp instanceof PEAR_Error) {
                            $startStamp = time();
                        }
                        $endStamp = $vFb->getAttribute('DTEND');
                        if ($endStamp instanceof PEAR_Error) {
                            $duration = $vFb->getAttribute('DURATION');
                            if ($duration instanceof PEAR_Error) {
                                $endStamp = $startStamp + (60 * 24 * 3600);
                            } else {
                                $endStamp = $startStamp + $duration;
                            }
                        }
                    }
                    $vfb_reply = $registry->call('calendar/getFreeBusy',
                                                 array('startStamp' => $startStamp,
                                                       'endStamp' => $endStamp));
                    // Find out who we are and update status.
                    $identity = Horde_Prefs_Identity::singleton();
                    $email = $identity->getFromAddress();

                    // Build the reply.
                    $msg_headers = new Horde_Mime_Headers();
                    $vCal = new Horde_iCalendar();
                    $vCal->setAttribute('PRODID', '-//The Horde Project//' . $msg_headers->getUserAgent() . '//EN');
                    $vCal->setAttribute('METHOD', 'REPLY');
                    $vCal->addComponent($vfb_reply);

                    $message = _("Attached is a reply to a calendar request you sent.");
                    $body = new Horde_Mime_Part('text/plain',
                                          Horde_String::wrap($message, 76, "\n"),
                                          $charset);

                    $ics = new Horde_Mime_Part('text/calendar', $vCal->exportvCalendar());
                    $ics->setName('icalendar.ics');
                    $ics->setContentTypeParameter('METHOD', 'REPLY');
                    $ics->setCharset($charset);

                    $mime = new Horde_Mime_Part();
                    $mime->addPart($body);
                    $mime->addPart($ics);

                    // Build the reply headers.
                    $msg_headers->addReceivedHeader();
                    $msg_headers->addMessageIdHeader();
                    $msg_headers->addHeader('Date', date('r'));
                    $msg_headers->addHeader('From', $email);
                    $msg_headers->addHeader('To', $organizerEmail);

                    $identity->setDefault(Horde_Util::getFormData('identity'));
                    $replyto = $identity->getValue('replyto_addr');
                    if (!empty($replyto) && ($replyto != $email)) {
                        $msg_headers->addHeader('Reply-to', $replyto);
                    }
                    $msg_headers->addHeader('Subject', Horde_Mime::encode(_("Free/Busy Request Response"), $charset));

                    // Send the reply.
                    $mail_driver = IMP_Compose::getMailDriver();
                    try {
                        $mime->send($organizerEmail, $msg_headers,
                                    $mail_driver['driver'],
                                    $mail_driver['params']);
                        $msgs[] = array('success', _("Reply Sent."));
                    } catch (Horde_Mime_Exception $e) {
                        $msgs[] = array('error', sprintf(_("Error sending reply: %s."), $e->getMessage()));
                    }
                } else {
                    $msgs[] = array('warning', _("Invalid Action selected for this component."));
                }
                break;

            case 'nosup':
                // vFreebusy request.
            default:
                $msgs[] = array('warning', _("This action is not yet implemented."));
                break;
            }
        }

        // Create the HTML to display the iCal file.
        $html = '';
        if ($_SESSION['imp']['view'] == 'imp') {
            $html .= '<form method="post" name="iCal" action="' . (IMP::selfUrl()) . '">';
        }

        foreach ($components as $key => $component) {
            switch ($component->getType()) {
            case 'vEvent':
                $html .= $this->_vEvent($component, $key, $method, $msgs);
                break;

            case 'vTodo':
                $html .= $this->_vTodo($component, $key, $method, $msgs);
                break;

            case 'vTimeZone':
                // Ignore them.
                break;

            case 'vFreebusy':
                $html .= $this->_vFreebusy($component, $key, $method, $msgs);
                break;

            // @todo: handle stray vcards here as well.
            default:
                $html .= sprintf(_("Unhandled component of type: %s"), $component->getType());
            }
        }

        // Need to work out if we are inline and actually need this.
        if ($_SESSION['imp']['view'] == 'imp') {
            $html .= '</form>';
        }

        return array(
            $mime_id => array(
                'data' => $html,
                'status' => array(),
                'type' => 'text/html; charset=' . $charset
            )
        );
    }

    /**
     * Return the html for a vFreebusy.
     */
    protected function _vFreebusy($vfb, $id, $method, $msgs)
    {
        global $registry, $prefs;

        $desc = $html = '';
        $sender = $vfb->getName();

        switch ($method) {
        case 'PUBLISH':
            $desc = _("%s has sent you free/busy information.");
            break;

        case 'REQUEST':
            $hdrs = $this->_params['contents']->getHeaderOb();
            $sender = $hdrs->getValue('From');
            $desc = _("%s requests your free/busy information.");
            break;

        case 'REPLY':
            $desc = _("%s has replied to a free/busy request.");
            break;
        }

        $html .= '<h1 class="header">' . sprintf($desc, $sender) . '</h1>';

        foreach ($msgs as $msg) {
            $html .= '<p class="notice">' . Horde::img('alerts/' . $msg[0] . '.png', '', null, $registry->getImageDir('horde')) . $msg[1] . '</p>';
        }

        $start = $vfb->getAttribute('DTSTART');
        if (!($start instanceof PEAR_Error)) {
            if (is_array($start)) {
                $html .= '<p><strong>' . _("Start:") . '</strong> ' . strftime($prefs->getValue('date_format'), mktime(0, 0, 0, $start['month'], $start['mday'], $start['year'])) . '</p>';
            } else {
                $html .= '<p><strong>' . _("Start:") . '</strong> ' . strftime($prefs->getValue('date_format'), $start) . ' ' . date($prefs->getValue('twentyFour') ? ' G:i' : ' g:i a', $start) . '</p>';
            }
        }

        $end = $vfb->getAttribute('DTEND');
        if (!($end instanceof PEAR_Error)) {
            if (is_array($end)) {
                $html .= '<p><strong>' . _("End:") . '</strong> ' . strftime($prefs->getValue('date_format'), mktime(0, 0, 0, $end['month'], $end['mday'], $end['year'])) . '</p>';
            } else {
                $html .= '<p><strong>' . _("End:") . '</strong> ' . strftime($prefs->getValue('date_format'), $end) . ' ' . date($prefs->getValue('twentyFour') ? ' G:i' : ' g:i a', $end) . '</p>';
            }
        }

        if ($_SESSION['imp']['view'] != 'imp') {
            return $html;
        }

        $html .= '<h2 class="smallheader">' . _("Actions") . '</h2>' .
            '<select name="itip_action[' . $id . ']">';

        switch ($method) {
        case 'PUBLISH':
            if ($registry->hasMethod('calendar/import_vfreebusy')) {
                $html .= '<option value="import">' .   _("Remember the free/busy information.") . '</option>';
            } else {
                $html .= '<option value="nosup">' . _("Reply with Not Supported Message") . '</option>';
            }
            break;

        case 'REQUEST':
            if ($registry->hasMethod('calendar/getFreeBusy')) {
                $html .= '<option value="reply">' .   _("Reply with requested free/busy information.") . '</option>' .
                    '<option value="reply2m">' . _("Reply with free/busy for next 2 months.") . '</option>';
            } else {
                $html .= '<option value="nosup">' . _("Reply with Not Supported Message") . '</option>';
            }

            $html .= '<option value="deny">' . _("Deny request for free/busy information") . '</option>';
            break;

        case 'REPLY':
            if ($registry->hasMethod('calendar/import_vfreebusy')) {
                $html .= '<option value="import">' .   _("Remember the free/busy information.") . '</option>';
            } else {
                $html .= '<option value="nosup">' . _("Reply with Not Supported Message") . '</option>';
            }
            break;
        }

        return $html . '</select> <input type="submit" class="button" value="' . _("Go") . '/>';
    }

    /**
     * Return the html for a vEvent.
     */
    protected function _vEvent($vevent, $id, $method, $msgs)
    {
        global $registry, $prefs;

        $desc = $html = '';
        $sender = $vevent->organizerName();
        $options = array();

        $attendees = $vevent->getAttribute('ATTENDEE');
        if (!($attendees instanceof PEAR_Error) &&
            !empty($attendees) &&
            !is_array($attendees)) {
            $attendees = array($attendees);
        }
        $attendee_params = $vevent->getAttribute('ATTENDEE', true);

        switch ($method) {
        case 'PUBLISH':
            $desc = _("%s wishes to make you aware of \"%s\".");
            if ($registry->hasMethod('calendar/import')) {
                $options[] = '<option value="import">' .   _("Add this to my calendar") . '</option>';
            }
            break;

        case 'REQUEST':
            // Check if this is an update.
            try {
                $registry->call('calendar/export', array($vevent->getAttribute('UID'), 'text/calendar'));
                $is_update = true;
                $desc = _("%s wants to notify you about changes of \"%s\".");
            } catch (Horde_Exception $e) {
                $is_update = false;

                // Check that you are one of the attendees here.
                $is_attendee = false;
                if (!($attendees instanceof PEAR_Error) && !empty($attendees)) {
                    $identity = Horde_Prefs_Identity::singleton(array('imp', 'imp'));
                    for ($i = 0, $c = count($attendees); $i < $c; ++$i) {
                        $attendee = parse_url($attendees[$i]);
                        if (!empty($attendee['path']) &&
                            $identity->hasAddress($attendee['path'])) {
                            $is_attendee = true;
                            break;
                        }
                    }
                }

                $desc = $is_attendee
                    ? _("%s requests your presence at \"%s\".")
                    : _("%s wishes to make you aware of \"%s\".");
            }
            if ($is_update && $registry->hasMethod('calendar/replace')) {
                $options[] = '<option value="accept-import">' . _("Accept and update in my calendar") . '</option>';
                $options[] = '<option value="import">' . _("Update in my calendar") . '</option>';
            } elseif ($registry->hasMethod('calendar/import')) {
                $options[] = '<option value="accept-import">' . _("Accept and add to my calendar") . '</option>';
                $options[] = '<option value="import">' . _("Add to my calendar") . '</option>';
            }
            $options[] = '<option value="accept">' . _("Accept request") . '</option>';
            $options[] = '<option value="tentative">' . _("Tentatively Accept request") . '</option>';
            $options[] = '<option value="deny">' . _("Deny request") . '</option>';
            // $options[] = '<option value="delegate">' . _("Delegate position") . '</option>';
            break;

        case 'ADD':
            $desc = _("%s wishes to ammend \"%s\".");
            if ($registry->hasMethod('calendar/import')) {
                $options[] = '<option value="import">' .   _("Update this event on my calendar") . '</option>';
            }
            break;

        case 'REFRESH':
            $desc = _("%s wishes to receive the latest information about \"%s\".");
            $options[] = '<option value="send">' . _("Send Latest Information") . '</option>';
            break;

        case 'REPLY':
            $hdrs = $this->_params['contents']->getHeaderOb();
            $desc = _("%s has replied to the invitation to \"%s\".");
            $sender = $hdrs->getValue('From');
            if ($registry->hasMethod('calendar/updateAttendee')) {
                $options[] = '<option value="update">' . _("Update respondent status") . '</option>';
            }
            break;

        case 'CANCEL':
            $instance = $vevent->getAttribute('RECURRENCE-ID');
            if ($instance instanceof PEAR_Error) {
                $desc = _("%s has cancelled \"%s\".");
                if ($registry->hasMethod('calendar/delete')) {
                    $options[] = '<option value="delete">' . _("Delete from my calendar") . '</option>';
                }
            } else {
                $desc = _("%s has cancelled an instance of the recurring \"%s\".");
                if ($registry->hasMethod('calendar/replace')) {
                    $options[] = '<option value="import">' . _("Update in my calendar") . '</option>';
                }
            }
            break;
        }

        $summary = $vevent->getAttribute('SUMMARY');
        if ($summary instanceof PEAR_Error) {
            $desc = sprintf($desc, htmlspecialchars($sender), _("Unknown Meeting"));
        } else {
            $desc = sprintf($desc, htmlspecialchars($sender), htmlspecialchars($summary));
        }

        $html .= '<h2 class="header">' . $desc . '</h2>';

        foreach ($msgs as $msg) {
            $html .= '<p class="notice">' . Horde::img('alerts/' . $msg[0] . '.png', '', null, $registry->getImageDir('horde')) . $msg[1] . '</p>';
        }

        $start = $vevent->getAttribute('DTSTART');
        if (!($start instanceof PEAR_Error)) {
            if (is_array($start)) {
                $html .= '<p><strong>' . _("Start:") . '</strong> ' . strftime($prefs->getValue('date_format'), mktime(0, 0, 0, $start['month'], $start['mday'], $start['year'])) . '</p>';
            } else {
                $html .= '<p><strong>' . _("Start:") . '</strong> ' . strftime($prefs->getValue('date_format'), $start) . ' ' . date($prefs->getValue('twentyFour') ? ' G:i' : ' g:i a', $start) . '</p>';
            }
        }

        $end = $vevent->getAttribute('DTEND');
        if (!($end instanceof PEAR_Error)) {
            if (is_array($end)) {
                $html .= '<p><strong>' . _("End:") . '</strong> ' . strftime($prefs->getValue('date_format'), mktime(0, 0, 0, $end['month'], $end['mday'], $end['year'])) . '</p>';
            } else {
                $html .= '<p><strong>' . _("End:") . '</strong> ' . strftime($prefs->getValue('date_format'), $end) . ' ' . date($prefs->getValue('twentyFour') ? ' G:i' : ' g:i a', $end) . '</p>';
            }
        }

        $sum = $vevent->getAttribute('SUMMARY');
        if (!($sum instanceof PEAR_Error)) {
            $html .= '<p><strong>' . _("Summary") . ':</strong> ' . htmlspecialchars($sum) . '</p>';
        } else {
            $html .= '<p><strong>' . _("Summary") . ':</strong> <em>' . _("None") . '</em></p>';
        }

        $desc = $vevent->getAttribute('DESCRIPTION');
        if (!($desc instanceof PEAR_Error)) {
            $html .= '<p><strong>' . _("Description") . ':</strong> ' . nl2br(htmlspecialchars($desc)) . '</p>';
        }

        $loc = $vevent->getAttribute('LOCATION');
        if (!($loc instanceof PEAR_Error)) {
            $html .= '<p><strong>' . _("Location") . ':</strong> ' . htmlspecialchars($loc) . '</p>';
        }

        if (!($attendees instanceof PEAR_Error) && !empty($attendees)) {
            $html .= '<h2 class="smallheader">' . _("Attendees") . '</h2>';

            $html .= '<table><thead class="leftAlign"><tr><th>' . _("Name") . '</th><th>' . _("Role") . '</th><th>' . _("Status") . '</th></tr></thead><tbody>';
            foreach ($attendees as $key => $attendee) {
                $attendee = parse_url($attendee);
                $attendee = empty($attendee['path']) ? _("Unknown") : $attendee['path'];

                if (!empty($attendee_params[$key]['CN'])) {
                    $attendee = $attendee_params[$key]['CN'];
                }

                $role = _("Required Participant");
                if (isset($attendee_params[$key]['ROLE'])) {
                    switch ($attendee_params[$key]['ROLE']) {
                    case 'CHAIR':
                        $role = _("Chair Person");
                        break;

                    case 'OPT-PARTICIPANT':
                        $role = _("Optional Participant");
                        break;

                    case 'NON-PARTICIPANT':
                        $role = _("Non Participant");
                        break;

                    case 'REQ-PARTICIPANT':
                    default:
                        // Already set above.
                        break;
                    }
                }

                $status = _("Awaiting Response");
                if (isset($attendee_params[$key]['PARTSTAT'])) {
                    $status = $this->_partstatToString($attendee_params[$key]['PARTSTAT'], $status);
                }

                $html .= '<tr><td>' . htmlspecialchars($attendee) . '</td><td>' . htmlspecialchars($role) . '</td><td>' . htmlspecialchars($status) . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        if ($registry->hasMethod('calendar/getFbCalendars') &&
            $registry->hasMethod('calendar/listEvents')) {
            try {
                $calendars = $registry->call('calendar/getFbCalendars');

                $vevent_allDay = true;
                $vevent_start = new Horde_Date($start);
                $vevent_end = new Horde_Date($end);
                // Check if it's an all-day event.
                if (is_array($start)) {
                    $vevent_end = $vevent_end->sub(1);
                } else {
                    $vevent_allDay = false;
                    $time_span_start = new Horde_Date($start);
                    $time_span_start = $time_span_start->sub($prefs->getValue('conflict_interval') * 60);
                    $time_span_end = new Horde_Date($end);
                    $time_span_end = $time_span_end->add($prefs->getValue('conflict_interval') * 60);
                }
                $events = $registry->call('calendar/listEvents', array($start, $vevent_end, $calendars, false));

                if (count($events)) {
                    $html .= '<h2 class="smallheader">' . _("Possible Conflicts") . '</h2><table id="itipconflicts">';
                    // TODO: Check if there are too many events to show.
                    foreach ($events as $calendar) {
                        foreach ($calendar as $event) {
                            if ($vevent_allDay || $event->isAllDay()) {
                                $html .= '<tr class="itipcollision">';
                            } else {
                                if ($event->end->compareDateTime($time_span_start) <= -1 ||
                                    $event->start->compareDateTime($time_span_end) >= 1) {
                                    continue;
                                }
                                if ($event->end->compareDateTime($vevent_start) <= -1 ||
                                    $event->start->compareDateTime($vevent_end) >= 1) {
                                    $html .= '<tr class="itipnearcollision">';
                                } else {
                                    $html .= '<tr class="itipcollision">';
                                }
                            }

                            $html .= '<td>'. $event->getTitle() . '</td><td>'
                                . $event->getTimeRange() . '</td></tr>';
                        }
                    }
                    $html .= '</table>';
                }
            } catch (Horde_Exception $e) {}
        }

        if ($_SESSION['imp']['view'] != 'imp') {
            return $html;
        }

        if ($options) {
            $html .= '<h2 class="smallheader">' . _("Actions") . '</h2>' .
                '<label for="action_' . $id . '" class="hidden">' . _("Actions") . '</label>' .
                '<select id="action_' . $id . '" name="itip_action[' . $id . ']">' .
                implode("\n", $options) .
                '</select> <input type="submit" class="button" value="' . _("Go") . '" />';
        }

        return $html;
    }

    /**
     * Returns the html for a vEvent.
     *
     * @todo IMP 5: move organizerName() from Horde_iCalendar_vevent to
     *       Horde_iCalendar
     */
    protected function _vTodo($vtodo, $id, $method, $msgs)
    {
        global $registry, $prefs;

        $desc = $html = '';
        $options = array();

        $organizer = $vtodo->getAttribute('ORGANIZER', true);
        if ($organizer instanceof PEAR_Error) {
            $sender = _("An unknown person");
        } else {
            if (isset($organizer[0]['CN'])) {
                $sender = $organizer[0]['CN'];
            } else {
                $organizer = parse_url($vtodo->getAttribute('ORGANIZER'));
                $sender = $organizer['path'];
            }
        }

        switch ($method) {
        case 'PUBLISH':
            $desc = _("%s wishes to make you aware of \"%s\".");
            if ($registry->hasMethod('tasks/import')) {
                $options[] = '<option value="import">' . _("Add this to my tasklist") . '</option>';
            }
            break;
        }

        $summary = $vtodo->getAttribute('SUMMARY');
        if ($summary instanceof PEAR_Error) {
            $desc = sprintf($desc, htmlspecialchars($sender), _("Unknown Task"));
        } else {
            $desc = sprintf($desc, htmlspecialchars($sender), htmlspecialchars($summary));
        }

        $html .= '<h2 class="header">' . $desc . '</h2>';

        foreach ($msgs as $msg) {
            $html .= '<p class="notice">' . Horde::img('alerts/' . $msg[0] . '.png', '', null, $registry->getImageDir('horde')) . $msg[1] . '</p>';
        }

        $priority = $vtodo->getAttribute('PRIORITY');
        if (!($priority instanceof PEAR_Error)) {
            $html .= '<p><strong>' . _("Priority") . ':</strong> ' . (int)$priority . '</p>';
        }

        $sum = $vtodo->getAttribute('SUMMARY');
        if (!($sum instanceof PEAR_Error)) {
            $html .= '<p><strong>' . _("Summary") . ':</strong> ' . htmlspecialchars($sum) . '</p>';
        } else {
            $html .= '<p><strong>' . _("Summary") . ':</strong> <em>' . _("None") . '</em></p>';
        }

        $desc = $vtodo->getAttribute('DESCRIPTION');
        if (!($desc instanceof PEAR_Error)) {
            $html .= '<p><strong>' . _("Description") . ':</strong> ' . nl2br(htmlspecialchars($desc)) . '</p>';
        }

        $attendees = $vtodo->getAttribute('ATTENDEE');
        $params = $vtodo->getAttribute('ATTENDEE', true);

        if (!($attendees instanceof PEAR_Error) && !empty($attendees)) {
            $html .= '<h2 class="smallheader">' . _("Attendees") . '</h2>';
            if (!is_array($attendees)) {
                $attendees = array($attendees);
            }

            $html .= '<table><thead class="leftAlign"><tr><th>' . _("Name") . '</th><th>' . _("Role") . '</th><th>' . _("Status") . '</th></tr></thead><tbody>';
            foreach ($attendees as $key => $attendee) {
                $attendee = parse_url($attendee);
                $attendee = $attendee['path'];

                if (isset($params[$key]['CN'])) {
                    $attendee = $params[$key]['CN'];
                }

                $role = _("Required Participant");
                if (isset($params[$key]['ROLE'])) {
                    switch ($params[$key]['ROLE']) {
                    case 'CHAIR':
                        $role = _("Chair Person");
                        break;

                    case 'OPT-PARTICIPANT':
                        $role = _("Optional Participant");
                        break;

                    case 'NON-PARTICIPANT':
                        $role = _("Non Participant");
                        break;

                    case 'REQ-PARTICIPANT':
                    default:
                        // Already set above.
                        break;
                    }
                }

                $status = _("Awaiting Response");
                if (isset($params[$key]['PARTSTAT'])) {
                    $status = $this->_partstatToString($params[$key]['PARTSTAT'], $status);
                }

                $html .= '<tr><td>' . htmlspecialchars($attendee) . '</td><td>' . htmlspecialchars($role) . '</td><td>' . htmlspecialchars($status) . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        if ($_SESSION['imp']['view'] != 'imp') {
            return $html;
        }

        if ($options) {
            $html .= '<h2 class="smallheader">' . _("Actions") . '</h2>' .
                '<select name="itip_action[' . $id . ']">' .
                implode("\n", $options) .
                '</select> <input type="submit" class="button" value="' . _("Go") . '" />';
        }

        return $html;
    }

    /**
     * Translate the Participation status to string.
     *
     * @param string $value    The value of PARTSTAT.
     * @param string $default  The value to return as default.
     *
     * @return string   The translated string.
     */
    protected function _partstatToString($value, $default = null)
    {
        switch ($value) {
        case 'ACCEPTED':
            return _("Accepted");

        case 'DECLINED':
            return _("Declined");

        case 'TENTATIVE':
            return _("Tentatively Accepted");

        case 'DELEGATED':
            return _("Delegated");

        case 'COMPLETED':
            return _("Completed");

        case 'IN-PROCESS':
            return _("In Process");

        case 'NEEDS-ACTION':
        default:
            return is_null($default) ? _("Needs Action") : $default;
        }
    }
}
