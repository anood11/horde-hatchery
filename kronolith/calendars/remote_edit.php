<?php
/**
 * Copyright 2002-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author Chuck Hagenbuch <chuck@horde.org>
 */

require_once dirname(__FILE__) . '/../lib/base.php';
require_once KRONOLITH_BASE . '/lib/Forms/EditRemoteCalendar.php';

// Exit if this isn't an authenticated user or if the user can't
// subscribe to remote calendars (remote_cals is locked).
if (!Horde_Auth::getAuth() || $prefs->isLocked('remote_cals')) {
    header('Location: ' . Horde::applicationUrl($prefs->getValue('defaultview') . '.php', true));
    exit;
}

$vars = Horde_Variables::getDefaultVariables();
$url = $vars->get('url');

$remote_calendar = null;
$remote_calendars = unserialize($GLOBALS['prefs']->getValue('remote_cals'));
foreach ($remote_calendars as $key => $calendar) {
    if ($calendar['url'] == $url) {
        $remote_calendar = $calendar;
        break;
    }
}
if (is_null($remote_calendar)) {
    $notification->push(_("The remote calendar was not found."), 'horde.error');
    header('Location: ' . Horde::applicationUrl('calendars/', true));
    exit;
}

$form = new Kronolith_EditRemoteCalendarForm($vars, $remote_calendar);

// Execute if the form is valid.
if ($form->validate($vars)) {
    $result = $form->execute();
    if (is_a($result, 'PEAR_Error')) {
        $notification->push($result, 'horde.error');
    } else {
        $notification->push(sprintf(_("The calendar \"%s\" has been saved."), $vars->get('name')), 'horde.success');
    }

    header('Location: ' . Horde::applicationUrl('calendars/', true));
    exit;
}

$key = Horde_Auth::getCredential('password');
$username = $calendar['user'];
$password = $calendar['password'];
if ($key) {
    $username = Horde_Secret::read($key, base64_decode($username));
    $password = Horde_Secret::read($key, base64_decode($password));
}

$vars->set('name', $calendar['name']);
$vars->set('url', $calendar['url']);
if (isset($calendar['desc'])) {
    $vars->set('desc', $calendar['desc']);
}
if (isset($calendar['color'])) {
    $vars->set('color', $calendar['color']);
}
$vars->set('user', $username);
$vars->set('password', $password);
$title = $form->getTitle();
require KRONOLITH_TEMPLATES . '/common-header.inc';
require KRONOLITH_TEMPLATES . '/menu.inc';
echo $form->renderActive($form->getRenderer(), $vars, 'remote_edit.php', 'post');
require $registry->get('templates', 'horde') . '/common-footer.inc';
