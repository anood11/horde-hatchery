<?php
/**
 * $Id: friends.php 983 2008-10-09 09:06:53Z duck $
 *
 * Copyright Obala d.o.o. (www.obala.si)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author Duck <duck@obala.net>
 * @package Folks
 */

require_once dirname(__FILE__) . '/lib/base.php';
require_once FOLKS_BASE . '/lib/Forms/Activity.php';

if (!Horde_Auth::isAuthenticated()) {
    Horde_Auth::authenticateFailure('folks');
}

$title = _("Friends");

$vars = Horde_Variables::getDefaultVariables();
$form = new Folks_Activity_Form($vars, _("What are you doing right now?"), 'short');
if ($form->validate()) {
    $result = $form->execute();
    if ($result instanceof PEAR_Error) {
        $notification->push($result);
    } else {
        $notification->push(_("Activity successfully posted"), 'horde.success');
        header('Location: ' . Horde::applicationUrl('friends.php'));
        exit;
    }
}

// Load driver
require_once FOLKS_BASE . '/lib/Friends.php';
$friends = Folks_Friends::singleton();

// Get friends
$friend_list = $friends->getFriends();
if ($friend_list instanceof PEAR_Error) {
    $notification->push($friend_list);
    $friend_list = array();
}

// Get friends activities
$firendActivities = array();
foreach ($friend_list as $user) {
    $activities = $folks_driver->getActivity($user);
    if ($activities instanceof PEAR_Error) {
        continue;
    }
    foreach ($activities as $activity) {
        $firendActivities[$activity['activity_date']] = $activity;
    }
}
krsort($firendActivities);
$firendActivities = array_slice($firendActivities, 0, 30);

// Own activities
$activities = $folks_driver->getActivity(Horde_Auth::getAuth());
if ($activities instanceof PEAR_Error) {
    $notification->push($activities);
    header('Location: ' . Folks::getUrlFor('list', 'list'));
    exit;
}

Horde::addScriptFile('stripe.js', 'horde');

require FOLKS_TEMPLATES . '/common-header.inc';
require FOLKS_TEMPLATES . '/menu.inc';

require FOLKS_TEMPLATES . '/friends/friends.php';

require $registry->get('templates', 'horde') . '/common-footer.inc';
