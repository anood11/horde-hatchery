<?php
/**
 * The IMP_Spam:: class contains functions related to reporting spam
 * messages in IMP.
 *
 * Copyright 2004-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@horde.org>
 * @package IMP
 */
class IMP_Spam
{
    /**
     * Reports a list of messages as spam, based on the local configuration
     * parameters.
     *
     * @param mixed $indices  See IMP::parseIndicesList().
     * @param string $action  Either 'spam' or 'notspam'.
     *
     * @return integer  1 if messages have been deleted, 2 if messages have
     *                  been moved.
     */
    static public function reportSpam($indices, $action)
    {
        global $notification;

        /* Abort immediately if spam reporting has not been enabled, or if
         * there are no messages. */
        if (empty($GLOBALS['conf'][$action]['reporting']) ||
            !($msgList = IMP::parseIndicesList($indices))) {
            return 0;
        }

        $report_count = 0;

        foreach ($msgList as $mbox => $msgIndices) {
            try {
                $GLOBALS['imp_imap']->checkUidvalidity($mbox);
            } catch (Horde_Exception $e) {
                continue;
            }

            foreach ($msgIndices as $idx) {
                /* Fetch the raw message contents (headers and complete
                 * body). */
                try {
                    $imp_contents = IMP_Contents::singleton($idx . IMP::IDX_SEP . $mbox);
                } catch (Horde_Exception $e) {
                    continue;
                }

                $raw_msg = $to = null;
                $report_flag = false;

                /* If a (not)spam reporting program has been provided, use
                 * it. */
                if (!empty($GLOBALS['conf'][$action]['program'])) {
                    $raw_msg = $imp_contents->fullMessageText(array('stream' => true));

                    /* Use a pipe to write the message contents. This should
                     * be secure. */
                    $prog = str_replace(array('%u','%l', '%d'),
                        array(
                            escapeshellarg(Horde_Auth::getAuth()),
                            escapeshellarg(Horde_Auth::getBareAuth()),
                            escapeshellarg(Horde_Auth::getAuthDomain())
                        ), $GLOBALS['conf'][$action]['program']);
                    $proc = proc_open($prog,
                        array(
                            0 => array('pipe', 'r'),
                            1 => array('pipe', 'w'),
                            2 => array('pipe', 'w')
                        ), $pipes);
                    if (!is_resource($proc)) {
                        Horde::logMessage('Cannot open process ' . $prog, __FILE__, __LINE__, PEAR_LOG_ERR);
                        return 0;
                    }
                    stream_copy_to_stream($raw_msg, $pipes[0]);
                    fclose($pipes[0]);
                    $stderr = '';
                    while (!feof($pipes[2])) {
                        $stderr .= fgets($pipes[2]);
                    }
                    fclose($pipes[2]);
                    if (!empty($stderr)) {
                        Horde::logMessage('Error reporting spam: ' . $stderr, __FILE__, __LINE__, PEAR_LOG_ERR);
                    }
                    proc_close($proc);
                    $report_flag = true;
                }

                /* If a (not)spam reporting email address has been provided,
                 * use it. */
                if (!empty($GLOBALS['conf'][$action]['email'])) {
                    $to = $GLOBALS['conf'][$action]['email'];
                } else {
                    /* Call the email generation hook, if requested. */
                    try {
                        $to = Horde::callHook('spam_email', array($action), 'imp');
                    } catch (Horde_Exception_HookNotSet $e) {}
                }

                if ($to) {
                    if (!isset($raw_msg)) {
                        $raw_msg = $imp_contents->fullMessageText(array('stream' => true));
                    }

                    if (!isset($imp_compose)) {
                        $imp_compose = IMP_Compose::singleton();
                        $identity = Horde_Prefs_Identity::singleton(array('imp', 'imp'));
                        try {
                            $from_line = $identity->getFromLine();
                        } catch (Horde_Exception $e) {
                            $from_line = null;
                        }
                    }

                    /* Build the MIME structure. */
                    $mime = new Horde_Mime_Part();
                    $mime->setType('multipart/digest');

                    $rfc822 = new Horde_Mime_Part();
                    $rfc822->setType('message/rfc822');
                    $rfc822->setContents($raw_msg);
                    $mime->addPart($rfc822);

                    $spam_headers = new Horde_Mime_Headers();
                    $spam_headers->addMessageIdHeader();
                    $spam_headers->addHeader('Date', date('r'));
                    $spam_headers->addHeader('To', $to);
                    if (!is_null($from_line)) {
                        $spam_headers->addHeader('From', $from_line);
                    }
                    $spam_headers->addHeader('Subject', sprintf(_("%s report from %s"), $action, Horde_Auth::getAuth()));

                    /* Send the message. */
                    try {
                        $imp_compose->sendMessage($to, $spam_headers, $mime, Horde_Nls::getCharset());
                        $report_flag = true;
                    } catch (IMP_Compose_Exception $e) {
                        Horde::logMessage($e, __FILE__, __LINE__, PEAR_LOG_ERR);
                    }
                }

                if ($report_flag) {
                    ++$report_count;
                }
            }
        }

        if (!$report_count) {
            return 0;
        }

        /* Report what we've done. */
        if ($report_count == 1) {
            $hdrs = $imp_contents->getHeaderOb();
            $subject = Horde_String::truncate($hdrs->getValue('subject'));

            switch ($action) {
            case 'spam':
                $msg = sprintf(_("The message \"%s\" has been reported as spam."), $subject);
                break;

            case 'notspam':
                $msg = sprintf(_("The message \"%s\" has been reported as innocent."), $subject);
                break;
            }
        } elseif ($action == 'spam') {
            $msg = sprintf(_("%d messages have been reported as spam."), $report_count);
        } else {
            $msg = sprintf(_("%d messages have been reported as innocent."), $report_count);
        }
        $notification->push($msg, 'horde.message');

        /* Delete/move message after report. */
        switch ($action) {
        case 'spam':
            if ($result = $GLOBALS['prefs']->getValue('delete_spam_after_report')) {
                $imp_message = IMP_Message::singleton();
                switch ($result) {
                case 1:
                    $msg_count = $imp_message->delete($indices);
                    if ($msg_count === false) {
                        $result = 0;
                    } else {
                        if ($msg_count == 1) {
                            $notification->push(_("The message has been deleted."), 'horde.message');
                        } else {
                            $notification->push(sprintf(_("%d messages have been deleted."), $msg_count), 'horde.message');
                        }
                    }
                    break;

                case 2:
                    $targetMbox = IMP::folderPref($GLOBALS['prefs']->getValue('spam_folder'), true);
                    if ($targetMbox) {
                        if (!$imp_message->copy($targetMbox, 'move', $indices, true)) {
                            $result = 0;
                        }
                    } else {
                        $notification->push(_("Could not move message to spam mailbox - no spam mailbox defined in preferences."), 'horde.error');
                        $result = 0;
                    }
                    break;
                }
            }
            break;

        case 'notspam':
            if ($result = $GLOBALS['prefs']->getValue('move_ham_after_report')) {
                $imp_message = IMP_Message::singleton();
                if (!$imp_message->copy('INBOX', 'move', $indices)) {
                    $result = 0;
                }
            }
            break;
        }

        return $result;
    }

}
