<?php
/**
 * Copyright 1999-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @package Kronolith
 */

require_once dirname(__FILE__) . '/lib/base.php';

$viewName = Horde_Util::getFormData('view', 'Event');
$view = Kronolith::getView($viewName);
if (is_a($view->event, 'PEAR_Error')) {
    $notification->push($view->event, 'horde.error');
    header('Location: ' . Horde::applicationUrl($prefs->getValue('defaultview') . '.php', true));
    exit;
}

switch ($viewName) {
case 'DeleteEvent':
    /* Shortcut when we're deleting events and don't want confirmation. */
    if (!$view->event->recurs() &&
        !($prefs->getValue('confirm_delete') ||
          Horde_Util::getFormData('confirm'))) {
        header('Location: ' . Horde::applicationUrl('delete.php?' . $_SERVER['QUERY_STRING'], true));
        exit;
    }
    break;

case 'EditEvent':
    if ($view->event->isPrivate() &&
        $view->event->getCreatorId() != Horde_Auth::getAuth()) {
        $url = Horde_Util::getFormData('url');
        if (empty($url)) {
            $url = Horde::applicationUrl($prefs->getValue('defaultview') . '.php', true);
        }
        header('Location: ' . Horde_Util::addParameter($url, 'unique', hash('md5', microtime()), false));
        exit;
    }
    break;
}

$title = $view->getTitle();
require KRONOLITH_TEMPLATES . '/common-header.inc';
require KRONOLITH_TEMPLATES . '/menu.inc';

echo '<div id="page">';
Kronolith::eventTabs($viewName, $view->event);
$view->html();
echo '</div>';
require $registry->get('templates', 'horde') . '/common-footer.inc';
