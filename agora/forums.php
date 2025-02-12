<?php
/**
 * The Agora script to display a list of forums.
 *
 * $Horde: agora/forums.php,v 1.71 2009-12-10 17:42:30 jan Exp $
 *
 * Copyright 2003-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author Jan Schneider <jan@horde.org>
 * @author Marko Djukic <marko@oblo.com>
 */

require_once dirname(__FILE__) . '/lib/base.php';

/* Set up the forums object. */
$scope = Horde_Util::getGet('scope', 'agora');
$forums = Agora_Messages::singleton($scope);

/* Set up actions */
if (Horde_Auth::isAdmin()) {
    $url = Horde::applicationUrl('forums.php');
    foreach ($registry->listApps(array('hidden', 'notoolbar', 'active')) as $app) {
        if ($registry->hasMethod('hasComments', $app) &&
            $registry->callByPackage($app, 'hasComments') === true) {
            $app_name = $registry->get('name', $app);
            $actions[] = Horde::link(Horde_Util::addParameter($url, 'scope', $app), $app_name) . $app_name . '</a>';
        }
    }
}

/* Get the sorting. */
$sort_by = Agora::getSortBy('forums');
$sort_dir = Agora::getSortDir('forums');

/* Which forums page are we on?  Default to page 0. */
$forum_page = Horde_Util::getFormData('forum_page', 0);
$forums_per_page = $prefs->getValue('forums_per_page');
$forum_start = $forum_page * $forums_per_page;

/* Get the list of forums. */
$forums_list = $forums->getForums(0, true, $sort_by, $sort_dir, true, $forum_start, $forums_per_page);
if ($forums_list instanceof PEAR_Error) {
    Horde::fatal($forums_list, __FILE__, __LINE__);
} elseif (empty($forums_list)) {
    $forums_count = 0;
} else {
    $forums_count = $forums->countForums();
}

/* Set up the column headers. */
$col_headers = array('forum_name' => _("Forum"), 'forum_description' => _("Description"), 'message_count' => _("Posts"), 'thread_count' => _("Threads"), 'message_timestamp' => _("Last Post"), 'message_author' => _("Posted by"), 'message_date' => _("Date"));
$col_headers = Agora::formatColumnHeaders($col_headers, $sort_by, $sort_dir, 'forums');

/* Set up the template tags. */
$view = new Agora_View();
$view->col_headers = $col_headers;
$view->forums_list = $forums_list;
$view->menu = Agora::getMenu('string');
$view->notify = Horde_Util::bufferOutput(array($notification, 'notify'), array('listeners' => 'status'));
$view->actions = empty($actions) ? null : $actions;

/* Set up pager. */
$vars = Horde_Variables::getDefaultVariables();
$pager_ob = new Horde_Ui_Pager('forum_page', $vars, array('num' => $forums_count, 'url' => 'forums.php', 'perpage' => $forums_per_page));
$pager_ob->preserve('scope', $scope);
$view->pager_link = $pager_ob->render();

$title = _("All Forums");
require AGORA_TEMPLATES . '/common-header.inc';
echo $view->render('forums.html.php');
require $registry->get('templates', 'horde') . '/common-footer.inc';
