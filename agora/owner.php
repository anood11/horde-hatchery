<?php
/**
 * The Agora script to display a list of forums.
 *
 * $Horde: agora/owner.php,v 1.7 2009/09/29 13:43:15 duck Exp $
 *
 * Copyright 2003-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author Duck  <duck@oabla.net>
 */

require_once dirname(__FILE__) . '/lib/base.php';

/* Only authenticated users should be using this. */
if (!Horde_Auth::isAuthenticated()) {
    $notification->push(_("You are not authorised for this action."), 'horde.warning');
    Horde_Auth::authenticateFailure('agora');
}

/* Default to agora and current user if is not an admin. */
$scope = Horde_Util::getGet('scope', 'agora');
$owner = Horde_Auth::isAdmin() ? Horde_Util::getGet('owner', Horde_Auth::getAuth()) : Horde_Auth::getAuth();

/* Get the sorting. */
$sort_by = Agora::getSortBy('threads');
$sort_dir = Agora::getSortDir('threads');

require AGORA_TEMPLATES . '/common-header.inc';

echo '<div id="menu">' . Agora::getMenu('string') . '</div>';
$notification->notify(array('listeners' => 'status'));
echo '<h1>' . sprintf(_("Last posts in forums owned by %s"), $owner) . '</h1>';

foreach ($registry->listApps() as $scope) {
    if ($scope == 'agora' || ($registry->hasMethod('hasComments', $scope) &&
        $registry->callByPackage($scope, 'hasComments') === true)) {
        $scope_name = $registry->get('name', $scope);
        $forums = Agora_Messages::singleton($scope);
        $threads = $forums->getThreadsByForumOwner($owner, 0, false, $sort_by, $sort_dir, false, 0, 5);
        echo '<h1 class="header">' . $scope_name  . '</h1>';

        if ($threads instanceof PEAR_Error) {
            echo $threads->getMessage();
        } elseif (empty($threads)) {
            echo _("No threads");
        } else {
            $link_back = $registry->hasMethod('show', $scope);
            $url = Horde::applicationUrl('agora/messages/index.php');

            /* link threads if possible */
            foreach ($threads as &$thread) {
                if ($link_back) {
                    $thread['link'] = Horde::link($registry->linkByPackage($scope, 'show', array('id' => $thread['forum_name'])));
                } else {
                    $thread['link'] = Horde::link(Agora::setAgoraId($thread['forum_id'], $thread['message_id'], $url, $scope, false));
                }
            }

            /* Set up the column headers. */
            $col_headers = array('message_subject' => _("Subject"), 'message_author' => _("Posted by"), 'message_timestamp' => _("Date"));
            $col_headers = Agora::formatColumnHeaders($col_headers, $sort_by, $sort_dir, 'threads');

            /* Set up the template tags. */
            $view = new Agora_View();
            $view->col_headers = $col_headers;
            $view->threads = $threads;

            echo $view->render('block/threads.html.php');
        }

        echo '<br />';
    }
}

require $registry->get('templates', 'horde') . '/common-footer.inc';
