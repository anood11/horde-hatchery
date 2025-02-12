<?php
/**
 * The Agora script ban users from a specific forum.
 *
 * Copyright 2006-2009 The Horde Project (http://www.horde.org/)
 *
 * $Horde: agora/ban.php,v 1.15 2009-12-01 12:52:38 jan Exp $
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

define('AGORA_BASE', dirname(__FILE__));
require_once AGORA_BASE . '/lib/base.php';
require_once AGORA_BASE . '/lib/Messages.php';


/* Make sure we have a forum id. */
list($forum_id, , $scope) = Agora::getAgoraId();
$forums = &Agora_Messages::singleton($scope, $forum_id);
if ($forums instanceof PEAR_Error) {
    $notification->push($forums->message, 'horde.error');
    header('Location: ' . Horde::applicationUrl('forums.php'));
    exit;
}

/* Check permissions */
if (!$forums->hasPermission(Horde_Perms::DELETE)) {
    $notification->push(sprintf(_("You don't have permissions to ban users from forum %s."), $forum_id), 'horde.warning');
    header('Location: ' . Horde::applicationUrl('forums.php'));
    exit;
}

/* Ban action */
if (($action = Horde_Util::getFormData('action')) !== null) {
    $user = Horde_Util::getFormData('user');
    $result = $forums->updateBan($user, $forum_id, $action);
    if ($result instanceof PEAR_Error) {
        $notification->push($result->getMessage(), 'horde.error');
    }

    $url = Agora::setAgoraId($forum_id, null, Horde::applicationUrl('ban.php'), $scope);
    header('Location: ' . $url);
    exit;
}

/* Get the list of banned users. */
$delete = Horde_Util::addParameter(Horde::applicationUrl('ban.php'),
                            array('action' => 'delete',
                                  'scope' => $scope,
                                  'forum_id' => $forum_id));
$banned = $forums->getBanned();
foreach ($banned as $user => $level) {
    $banned[$user] = Horde::link(Horde_Util::addParameter($delete, 'user', $user), _("Delete")) . $user . '</a>';
}

$title = _("Ban");
$vars = Horde_Variables::getDefaultVariables();
$form = new Horde_Form($vars, $title);
$form->addHidden('', 'scope', 'text', false);
$form->addHidden('', 'agora', 'text', false);
$form->addHidden('', 'action', 'text', false);
$vars->set('action', 'add');
$form->addVariable(_("User"), 'user', 'text', true);

$view = new Agora_View();
$view->menu = Agora::getMenu('string');
$view->formbox = Horde_Util::bufferOutput(array($form, 'renderActive'), null, null, 'ban.php', 'post');
$view->notify = Horde_Util::bufferOutput(array($notification, 'notify'), array('listeners' => 'status'));
$view->banned = $banned;
$view->forum = $forums->getForum();

require AGORA_TEMPLATES . '/common-header.inc';
echo $view->render('ban.html.php');
require $registry->get('templates', 'horde') . '/common-footer.inc';
