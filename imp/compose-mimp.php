<?php
/**
 * Minimalist (mimp) compose display page.
 *
 * Copyright 2002-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Michael Slusarz <slusarz@curecanti.org>
 * @package IMP
 */

function _getIMPContents($uid, $mailbox)
{
    if (empty($uid)) {
        return false;
    }
    try {
        $imp_contents = IMP_Contents::singleton($uid . IMP::IDX_SEP . $mailbox);
        return $imp_contents;
    } catch (Horde_Exception $e) {
        $GLOBALS['notification']->push(_("Could not retrieve the message from the mail server."), 'horde.error');
        return false;
    }
}

require_once dirname(__FILE__) . '/lib/Application.php';
new IMP_Application(array('init' => true, 'tz' => true));

/* The message text and headers. */
$msg = '';
$header = array();

/* Set the current identity. */
$identity = Horde_Prefs_Identity::singleton(array('imp', 'imp'));
if (!$prefs->isLocked('default_identity')) {
    $identity_id = Horde_Util::getFormData('identity');
    if (!is_null($identity_id)) {
        $identity->setDefault($identity_id);
    }
}

$save_sent_mail = $prefs->getValue('save_sent_mail');
$sent_mail_folder = $identity->getValue('sent_mail_folder');
$thismailbox = Horde_Util::getFormData('thismailbox');
$uid = Horde_Util::getFormData('uid');
$resume_draft = false;

/* Determine if mailboxes are readonly. */
$draft = IMP::folderPref($prefs->getValue('drafts_folder'), true);
$readonly_drafts = empty($draft) ? false : $imp_imap->isReadOnly($draft);
if ($imp_imap->isReadOnly($sent_mail_folder)) {
    $save_sent_mail = false;
}

/* Determine if compose mode is disabled. */
$compose_disable = !IMP::canCompose();

/* Initialize the IMP_Compose:: object. */
$imp_compose = IMP_Compose::singleton(Horde_Util::getFormData('composeCache'));

/* Run through the action handlers. */
$actionID = Horde_Util::getFormData('a');
switch ($actionID) {
// 'd' = draft
case 'd':
    try {
        $result = $imp_compose->resumeDraft($uid . IMP::IDX_SEP . $thismailbox);

        $msg = $result['msg'];
        $header = array_merge($header, $result['header']);
        if (!is_null($result['identity']) &&
            ($result['identity'] != $identity->getDefault()) &&
            !$prefs->isLocked('default_identity')) {
            $identity->setDefault($result['identity']);
            $sent_mail_folder = $identity->getValue('sent_mail_folder');
        }
        $resume_draft = true;
    } catch (IMP_Compose_Exception $e) {
        $notification->push($e, 'horde.error');
    }
    break;

case _("Expand Names"):
    $action = Horde_Util::getFormData('action');
    $imp_ui = new IMP_UI_Compose();
    $header['to'] = $imp_ui->expandAddresses(Horde_Util::getFormData('to'), $imp_compose);
    if ($action !== 'rc') {
        if ($prefs->getValue('compose_cc')) {
            $header['cc'] = $imp_ui->expandAddresses(Horde_Util::getFormData('cc'), $imp_compose);
        }
        if ($prefs->getValue('compose_bcc')) {
            $header['bcc'] = $imp_ui->expandAddresses(Horde_Util::getFormData('bcc'), $imp_compose);
        }
    }
    if (!is_null($action)) {
        $actionID = $action;
    }
    break;

// 'r' = reply
// 'rl' = reply to list
// 'ra' = reply to all
case 'r':
case 'ra':
case 'rl':
    if (!($imp_contents = _getIMPContents($uid, $thismailbox))) {
        break;
    }
    $actions = array('r' => 'reply', 'ra' => 'reply_all', 'rl' => 'reply_list');
    $reply_msg = $imp_compose->replyMessage($actions[$actionID], $imp_contents, Horde_Util::getFormData('to'));
    $header = $reply_msg['headers'];

    $notification->push(_("Reply text will be automatically appended to your outgoing message."), 'horde.message');
    break;

// 'f' = forward
case 'f':
    if (!($imp_contents = _getIMPContents($uid, $thismailbox))) {
        break;
    }
    $fwd_msg = $imp_compose->forwardMessage($imp_contents);
    $header = $fwd_msg['headers'];

    $notification->push(_("Forwarded message will be automatically added to your outgoing message."), 'horde.message');
    break;

case _("Redirect"):
    if (!($imp_contents = _getIMPContents($uid, $thismailbox))) {
        break;
    }

    $imp_ui = new IMP_UI_Compose();

    $f_to = $imp_ui->getAddressList(Horde_Util::getFormData('to'));

    try {
        $imp_ui->redirectMessage($f_to, $imp_compose, $imp_contents, Horde_Nls::getEmailCharset());
        if ($prefs->getValue('compose_confirm')) {
            $notification->push(_("Message redirected successfully."), 'horde.success');
        }
        require IMP_BASE . '/mailbox-mimp.php';
        exit;
    } catch (Horde_Exception $e) {
        $actionID = 'rc';
        $notification->push($e, 'horde.error');
    }
    break;

case _("Save Draft"):
case _("Send"):
    switch ($actionID) {
    case _("Save Draft"):
        if ($readonly_drafts) {
            break 2;
        }
        break;

    case _("Send"):
        if ($compose_disable) {
            break 2;
        }
        break;
    }

    $message = Horde_Util::getFormData('message', '');
    $f_to = Horde_Util::getFormData('to');
    $f_cc = $f_bcc = null;
    $header = array();

    $thismailbox = $imp_compose->getMetadata('mailbox');
    $uid = $imp_compose->getMetadata('uid');

    if ($ctype = $imp_compose->getMetadata('reply_type')) {
        if (!($imp_contents = _getIMPContents($uid, $thismailbox))) {
            break;
        }

        switch ($ctype) {
        case 'reply':
            $reply_msg = $imp_compose->replyMessage('reply', $imp_contents, $f_to);
            $msg = $reply_msg['body'];
            $message .= "\n" . $msg;
            break;

        case 'forward':
            $fwd_msg = $imp_compose->forwardMessage($imp_contents);
            $msg = $fwd_msg['body'];
            $message .= "\n" . $msg;
            $imp_compose->attachIMAPMessage(array($uid . IMP::IDX_SEP . $thismailbox), $header);
            break;
        }
    }

    try {
        $header['from'] = $identity->getFromLine(null, Horde_Util::getFormData('from'));
    } catch (Horde_Exception $e) {
        $header['from'] = '';
    }
    $header['replyto'] = $identity->getValue('replyto_addr');
    $header['subject'] = Horde_Util::getFormData('subject');

    $imp_ui = new IMP_UI_Compose();

    $header['to'] = $imp_ui->getAddressList(Horde_Util::getFormData('to'));
    if ($prefs->getValue('compose_cc')) {
        $header['cc'] = $imp_ui->getAddressList(Horde_Util::getFormData('cc'));
    }
    if ($prefs->getValue('compose_bcc')) {
        $header['bcc'] = $imp_ui->getAddressList(Horde_Util::getFormData('bcc'));
    }

    switch ($actionID) {
    case _("Save Draft"):
        try {
            $notification->push($imp_compose->saveDraft($header, $message, Horde_Nls::getCharset(), false), 'horde.success');
            $imp_compose->destroy();
            require IMP_BASE . '/mailbox-mimp.php';
            exit;
        } catch (IMP_Compose_Exception $e) {
            $notification->push($e, 'horde.error');
        }
        break;

    case _("Save"):
        $sig = $identity->getSignature();
        if (!empty($sig)) {
            $message .= "\n" . $sig;
        }

        $options = array(
            'save_sent' => $save_sent_mail,
            'sent_folder' => $sent_mail_folder,
            'readreceipt' => Horde_Util::getFormData('request_read_receipt')
        );

        try {
            if ($imp_compose->buildAndSendMessage($message, $header, Horde_Nls::getEmailCharset(), false, $options)) {
                $imp_compose->destroy();

                if (Horde_Util::getFormData('resume_draft') &&
                    $prefs->getValue('auto_delete_drafts')) {
                    $imp_message = IMP_Message::singleton();
                    $idx_array = array($uid . IMP::IDX_SEP . $thismailbox);
                    $delete_draft = $imp_message->delete($idx_array, array('nuke' => true));
                }

                $notification->push(_("Message sent successfully."), 'horde.success');
                require IMP_BASE . '/mailbox-mimp.php';
                exit;
            }
        } catch (IMP_Compose_Exception $e) {
            $notification->push($e, 'horde.error');
        }
        break;
    }
    break;
}

/* Get the message cache ID. */
$cacheID = $imp_compose->getCacheId();

$title = _("Message Composition");
$mimp_render = new Horde_Mobile();
$mimp_render->set('title', $title);

$select_list = $identity->getSelectList();

/* Grab any data that we were supplied with. */
if (empty($msg)) {
    $msg = Horde_Util::getFormData('message', '');
}
foreach (array('to', 'cc', 'bcc', 'subject') as $val) {
    if (empty($header[$val])) {
        $header[$val] = Horde_Util::getFormData($val);
    }
}

$menu = new Horde_Mobile_card('o', _("Menu"));
$mset = &$menu->add(new Horde_Mobile_linkset());
IMP_Mimp::addMIMPMenu($mset, 'compose');

if ($actionID == 'rc') {
    require IMP_TEMPLATES . '/compose/redirect-mimp.inc';
} else {
    require IMP_TEMPLATES . '/compose/compose-mimp.inc';
}
