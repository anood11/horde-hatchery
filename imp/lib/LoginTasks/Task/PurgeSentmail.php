<?php
/**
 * Login tasks module that purges old messages in the sent-mail folder.
 *
 * Copyright 2001-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@horde.org>
 * @author  Jan Schneider <jan@horde.org>
 * @package Horde_LoginTasks
 */
class IMP_LoginTasks_Task_PurgeSentmail extends Horde_LoginTasks_Task
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->active = $GLOBALS['prefs']->getValue('purge_sentmail');
        if ($this->active) {
            $this->interval = $GLOBALS['prefs']->getValue('purge_sentmail_interval');
            if ($GLOBALS['prefs']->isLocked('purge_sentmail')) {
                $this->display = Horde_LoginTasks::DISPLAY_NONE;
            }
        }
    }

    /**
     * Purge old messages in the sent-mail folder.
     *
     * @return boolean  Whether any messages were purged from the sent-mail
     *                  folder.
     */
    public function execute()
    {
        IMP::initialize();

        $imp_folder = IMP_Folder::singleton();
        $imp_message = IMP_Message::singleton();

        $mbox_list = $this->_getFolders();

        /* Get the current UNIX timestamp minus the number of days specified
         * in 'purge_sentmail_keep'.  If a message has a timestamp prior to
         * this value, it will be deleted. */
        $del_time = new Horde_Date(time() - ($GLOBALS['prefs']->getValue('purge_sentmail_keep') * 86400));

        foreach ($mbox_list as $mbox) {
            /* Make sure the sent-mail mailbox exists. */
            if (!$imp_folder->exists($mbox)) {
                continue;
            }

            /* Open the sent-mail mailbox and get the list of messages older
             * than 'purge_sentmail_keep' days. */
            $query = new Horde_Imap_Client_Search_Query();
            $query->dateSearch($del_time, Horde_Imap_Client_Search_Query::DATE_BEFORE);
            $msg_ids = $GLOBALS['imp_search']->runSearchQuery($query, $mbox);
            if (empty($msg_ids)) {
                continue;
            }

            /* Go through the message list and delete the messages. */
            if ($imp_message->delete(array($mbox => $msg_ids), array('nuke' => true))) {
                $msgcount = count($msg_ids);
                if ($msgcount == 1) {
                    $GLOBALS['notification']->push(sprintf(_("Purging 1 message from sent-mail folder %s."), IMP::displayFolder($mbox)), 'horde.message');
                } else {
                    $GLOBALS['notification']->push(sprintf(_("Purging %d messages from sent-mail folder."), $msgcount, IMP::displayFolder($mbox)), 'horde.message');
                }
            }
        }

        return true;
    }

    /**
     * Return information for the login task.
     *
     * @return string  Description of what the operation is going to do during
     *                 this login.
     */
    public function describe()
    {
        IMP::initialize();

        $mbox_list = array_map(array('IMP', 'displayFolder'), $this->_getFolders());

        return sprintf(_("All messages in the folder(s) \"%s\" older than %s days will be permanently deleted."),
                       implode(', ', $mbox_list),
                       $GLOBALS['prefs']->getValue('purge_sentmail_keep'));
    }

    /**
     * Returns the list of sent-mail folders.
     *
     * @return array  All sent-mail folders.
     */
    protected function _getFolders()
    {
        return Horde_Prefs_Identity::singleton(array('imp', 'imp'))->getAllSentmailfolders();
    }

}
