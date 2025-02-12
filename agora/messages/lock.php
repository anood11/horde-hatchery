<?php
/**
 * The Agora script to lock a message and prevent further posts to this thread.
 *
 * $Horde: agora/messages/lock.php,v 1.30 2009-12-01 12:52:38 jan Exp $
 *
 * Copyright 2003-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author Marko Djukic <marko@oblo.com>
 */

define('AGORA_BASE', dirname(__FILE__) . '/..');
require_once AGORA_BASE . '/lib/base.php';
require_once AGORA_BASE . '/lib/Messages.php';

/* Set up the messages object. */
list($forum_id, $message_id, $scope) = Agora::getAgoraId();
$messages = &Agora_Messages::singleton($scope, $forum_id);
if ($messages instanceof PEAR_Error) {
    $notification->push($messages->getMessage(), 'horde.warning');
    $url = Horde::applicationUrl('forums.php', true);
    header('Location: ' . $url);
    exit;
}

/* Get requested message, if fail then back to forums list. */
$message = $messages->getMessage($message_id);
if ($message instanceof PEAR_Error) {
    $notification->push(sprintf(_("Could not open the message. %s"), $message->getMessage()), 'horde.warning');
    header('Location: ' . Horde::applicationUrl('forums.php', true));
    exit;
}

/* Check delete permissions */
if (!$messages->hasPermission(Horde_Perms::DELETE)) {
    $notification->push(sprintf(_("You don't have permission to delete messages in forum %s."), $forum_id), 'horde.warning');
    $url = Agora::setAgoraId($forum_id, $message_id, Horde::applicationUrl('messages/index.php', true), $scope);
    header('Location: ' . $url);
    exit;
}

/* Get the form object. */
$vars = Horde_Variables::getDefaultVariables();
$form = new Horde_Form($vars, sprintf(_("Locking thread \"%s\""), $message['message_subject']));
$form->setButtons(_("Update"), true);
$form->addHidden('', 'agora', 'text', false);
$v = &$form->addVariable(_("Allow replies in this thread"), 'message_lock', 'radio', true, false, null, array(array('0' => _("Yes, allow replies"), '1' => _("No, do not allow replies"))));
$v->setDefault('0');

if ($form->validate()) {
    $form->getInfo($vars, $info);

    /* Try and delete this message. */
    $result = $messages->setThreadLock($message_id, $info['message_lock']);
    if ($result instanceof PEAR_Error) {
        $notification->push(sprintf(_("Could not lock the thread. %s"), $result->getMessage()), 'horde.error');
    } else {
        if ($info['message_lock']) {
            $notification->push(_("Thread locked."), 'horde.success');
        } else {
            $notification->push(_("Thread unlocked."), 'horde.success');
        }
        $url = Agora::setAgoraId($forum_id, $message_id, Horde::applicationUrl('messages/index.php', true));
        header('Location: ' . $url);
        exit;
    }
}

/* Set up template data. */
$view = new Agora_View();
$view->menu = Agora::getMenu('string');
$view->formbox = Horde_Util::bufferOutput(array($form, 'renderActive'), null, $vars, 'lock.php', 'post');
$view->notify = Horde_Util::bufferOutput(array($notification, 'notify'), array('listeners' => 'status'));
$view->message_subject = $message['message_subject'];
$view->message_author = $message['message_author'];
$view->message_date = strftime($prefs->getValue('date_format'), $message['message_timestamp']);
$view->message_body = Agora_Messages::formatBody($message['body']);

require AGORA_TEMPLATES . '/common-header.inc';
echo $view->render('messages/form.html.php');
require $registry->get('templates', 'horde') . '/common-footer.inc';
