<?php
/**
 * The IMP_Folder:: class provides a set of methods for dealing with folders,
 * accounting for subscription, errors, etc.
 *
 * @todo Don't use notification.
 *
 * Copyright 2000-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jon Parise <jon@horde.org>
 * @author  Michael Slusarz <slusarz@horde.org>
 * @package IMP
 */
class IMP_Folder
{
    /**
     * Singleton instance.
     *
     * @var IMP_Folder
     */
    static protected $_instance = null;

    /**
     * Keep around identical lists so that we don't hit the server more that
     * once in the same page for the same thing.
     *
     * @var array
     */
    protected $_listCache = null;

    /**
     * The cache ID used to store mailbox info.
     *
     * @var string
     */
    protected $_cacheid = null;

    /**
     * Returns a reference to the global IMP_Folder object, only creating it
     * if it doesn't already exist. This ensures that only one IMP_Folder
     * instance is instantiated for any given session.
     *
     * @return IMP_Folder  The IMP_Folder instance.
     */
    static public function singleton()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new IMP_Folder();
        }

        return self::$_instance;
    }

    /**
     * Constructor.
     */
    protected function __construct()
    {
        if (!empty($GLOBALS['conf']['server']['cache_folders'])) {
            $this->_cacheid = 'imp_folder_cache|' . Horde_Auth::getAuth();
        }
    }

    /**
     * Lists folders.
     *
     * @param array $filter  An list of mailboxes that should be left out of
     *                       the list (UTF7-IMAP).
     * @param boolean $sub   Should we list only subscribed folders?
     *
     * @return array  An array of folders, where each array element is an
     *                associative array containing three values:
     * <pre>
     * 'val' - (string)  Folder name (UTF7-IMAP)
     * 'label' - (string) Full-length folder name (system charset)
     * 'abbrev' - (string) Short (26 char) label (system charset)
     * </pre>
     */
    public function flist($filter = array(), $sub = null)
    {
        $inbox_entry = array(
            'INBOX' => array(
                'val' => 'INBOX',
                'label' => _("Inbox"),
                'abbrev' => _("Inbox")
            )
        );

        if ($_SESSION['imp']['protocol'] == 'pop') {
            return $inbox_entry;
        }

        if (is_null($sub)) {
            $sub = $GLOBALS['prefs']->getValue('subscribe');
        }

        /* Compute values that will uniquely identify this list. */
        $sig = hash('md5', serialize(array(intval($sub), $filter)));

        /* Either get the list from the cache, or go to the IMAP server to
           obtain it. */
        $cache = null;
        if (is_null($this->_listCache)) {
            if (!is_null($this->_cacheid) && ($cache = IMP::getCache())) {
                $ret = $cache->get($this->_cacheid, 3600);
                if (!empty($ret)) {
                    $this->_listCache = unserialize($ret);
                }
            }

            if (empty($this->_listCache)) {
                $this->_listCache = array();
            }
        }

        if (isset($this->_listCache[$sig])) {
            return $this->_listCache[$sig];
        }

        $imaptree = IMP_Imap_Tree::singleton();

        $list_mask = IMP_Imap_Tree::FLIST_CONTAINER | IMP_Imap_Tree::FLIST_OB;
        if (!$sub) {
            $list_mask |= IMP_Imap_Tree::FLIST_UNSUB;
        }
        $flist = $imaptree->folderList($list_mask);

        reset($flist);
        while (list(,$ob) = each($flist)) {
            if (in_array($ob['v'], $filter)) {
                continue;
            }

            $abbrev = $label = str_repeat(' ', 2 * $ob['c']) . $ob['l'];
            if (strlen($abbrev) > 26) {
                $abbrev = Horde_String::substr($abbrev, 0, 10) . '...' . Horde_String::substr($abbrev, -13, 13);
            }
            $list[$ob['v']] = array('val' => $imaptree->isContainer($ob) ? '' : $ob['v'], 'label' => $label, 'abbrev' => $abbrev);
        }

        /* Add the INBOX on top of list if not in the filter list. */
        if (!in_array('INBOX', $filter)) {
            $list = $inbox_entry + $list;
        }

        $this->_listCache[$sig] = $list;

        /* Save in cache, if needed. */
        if (!is_null($cache)) {
            $cache->set($this->_cacheid, serialize($this->_listCache), 3600);
        }

        return $list;
    }

    /**
     * Clears the flist folder cache.
     */
    public function clearFlistCache()
    {
        if (!is_null($this->_cacheid) && ($cache = IMP::getCache())) {
            $cache->expire($this->_cacheid);
        }
        $this->_listCache = array();
    }

    /**
     * Deletes one or more folders.
     *
     * @param array $folders  Folders to be deleted (UTF7-IMAP).
     * @param boolean $force  Delete folders even if fixed?
     *
     * @return boolean  Were folders successfully deleted?
     */
    public function delete($folders, $force = false)
    {
        global $conf, $notification;

        $return_value = true;
        $deleted = array();

        foreach ($folders as $folder) {
            if (!$force &&
                !empty($conf['server']['fixed_folders']) &&
                in_array(IMP::folderPref($folder, false), $conf['server']['fixed_folders'])) {
                $notification->push(sprintf(_("The folder \"%s\" may not be deleted."), IMP::displayFolder($folder)), 'horde.error');
                continue;
            }

            try {
                $GLOBALS['imp_imap']->ob()->deleteMailbox($folder);
                $notification->push(sprintf(_("The folder \"%s\" was successfully deleted."), IMP::displayFolder($folder)), 'horde.success');
                $deleted[] = $folder;
            } catch (Horde_Imap_Client_Exception $e) {
                $notification->push(sprintf(_("The folder \"%s\" was not deleted. This is what the server said"), IMP::displayFolder($folder)) . ': ' . $e->getMessage(), 'horde.error');
            }
        }

        if (!empty($deleted)) {
            /* Update the IMAP_Tree cache. */
            $imaptree = IMP_Imap_Tree::singleton();
            $imaptree->delete($deleted);

            $this->_onDelete($deleted);
        }

        return $return_value;
    }

    /**
     * Do the necessary cleanup/cache updates when deleting folders.
     *
     * @param array $deleted  The list of deleted folders.
     */
    protected function _onDelete($deleted)
    {
        /* Reset the folder cache. */
        $this->clearFlistCache();

        /* Recreate Virtual Folders. */
        $GLOBALS['imp_search']->initialize(true);

        /* Clear the folder from the sort prefs. */
        foreach ($deleted as $val) {
            IMP::setSort(null, null, $val, true);
        }
    }

    /**
     * Create a new IMAP folder if it does not already exist, and subcribe to
     * it as well if requested.
     *
     * @param string $folder      The folder to be created (UTF7-IMAP).
     * @param boolean $subscribe  Subscribe to folder?
     *
     * @return boolean  Whether or not the folder was successfully created.
     * @throws Horde_Exception
     */
    public function create($folder, $subscribe)
    {
        global $conf, $notification;

        /* Check permissions. */
        if (!$GLOBALS['perms']->hasAppPermission('create_folders')) {
            try {
                $message = Horde::callHook('perms_denied', array('imp:create_folders'));
            } catch (Horde_Exception_HookNotSet $e) {
                $message = @htmlspecialchars(_("You are not allowed to create folders."), ENT_COMPAT, Horde_Nls::getCharset());
            }
            $notification->push($message, 'horde.error', array('content.raw'));
            return false;
        } elseif (!$GLOBALS['perms']->hasAppPermission('max_folders')) {
            try {
                $message = Horde::callHook('perms_denied', array('imp:max_folders'));
            } catch (Horde_Exception_HookNotSet $e) {
                $message = @htmlspecialchars(sprintf(_("You are not allowed to create more than %d folders."), $GLOBALS['perms']->hasAppPermission('max_folders', array('opts' => array('value' => true)))), ENT_COMPAT, Horde_Nls::getCharset());
            }
            $notification->push($message, 'horde.error', array('content.raw'));
            return false;
        }

        /* Make sure we are not trying to create a duplicate folder */
        if ($this->exists($folder)) {
            $notification->push(sprintf(_("The folder \"%s\" already exists"), IMP::displayFolder($folder)), 'horde.warning');
            return false;
        }

        /* Attempt to create the mailbox. */
        try {
            $GLOBALS['imp_imap']->ob()->createMailbox($folder);
        } catch (Horde_Imap_Client_Exception $e) {
            $notification->push(sprintf(_("The folder \"%s\" was not created. This is what the server said"), IMP::displayFolder($folder)) . ': ' . $e->getMessage(), 'horde.error');
            return false;
        }

        $GLOBALS['notification']->push(sprintf(_("The folder \"%s\" was successfully created."), IMP::displayFolder($folder)), 'horde.success');

        /* Subscribe, if requested. */
        if ($subscribe) {
            $this->subscribe(array($folder));
        }

        /* Reset the folder cache. */
        $this->clearFlistCache();

        /* Update the IMAP_Tree object. */
        $imaptree = IMP_Imap_Tree::singleton();
        $imaptree->insert($folder);

        /* Recreate Virtual Folders. */
        $GLOBALS['imp_search']->initialize(true);

        return true;
    }

    /**
     * Finds out if a specific folder exists or not.
     *
     * @param string $folder  The folder name to be checked (UTF7-IMAP).
     *
     * @return boolean  Does the folder exist?
     */
    public function exists($folder)
    {
        $imaptree = IMP_Imap_Tree::singleton();
        $elt = $imaptree->get($folder);
        if ($elt) {
            return !$imaptree->isContainer($elt);
        }

        try {
            $ret = $GLOBALS['imp_imap']->ob()->listMailboxes($folder, array('flat' => true));
            return !empty($ret);
        } catch (Horde_Imap_Client_Exception $e) {
            return false;
        }
    }

    /**
     * Renames an IMAP folder. The subscription status remains the same.  All
     * subfolders will also be renamed.
     *
     * @param string $old     The old folder name (UTF7-IMAP).
     * @param string $new     The new folder name (UTF7-IMAP).
     * @param boolean $force  Rename folders even if they are fixed?
     *
     * @return boolean  Were all folder(s) successfully renamed?
     */
    public function rename($old, $new, $force = false)
    {
        /* Don't try to rename from or to an empty string. */
        if ((strlen($old) == 0) || (strlen($new) == 0)) {
            return false;
        }

        if (!$force &&
            !empty($GLOBALS['conf']['server']['fixed_folders']) &&
            in_array(IMP::folderPref($old, false), $GLOBALS['conf']['server']['fixed_folders'])) {
            $GLOBALS['notification']->push(sprintf(_("The folder \"%s\" may not be renamed."), IMP::displayFolder($old)), 'horde.error');
            return false;
        }

        $deleted = array($old);
        $inserted = array($new);

        $imaptree = IMP_Imap_Tree::singleton();

        /* Get list of any folders that are underneath this one. */
        $all_folders = array_merge(array($old), $imaptree->folderList(IMP_Imap_Tree::FLIST_UNSUB, $old));
        $sub_folders = $imaptree->folderList();

        try {
            $GLOBALS['imp_imap']->ob()->renameMailbox($old, $new);
        } catch (Horde_Imap_Client_Exception $e) {
            $GLOBALS['notification']->push(sprintf(_("Renaming \"%s\" to \"%s\" failed. This is what the server said"), IMP::displayFolder($old), IMP::displayFolder($new)) . ': ' . $e->getMessage(), 'horde.error');
            return false;
        }

        $GLOBALS['notification']->push(sprintf(_("The folder \"%s\" was successfully renamed to \"%s\"."), IMP::displayFolder($old), IMP::displayFolder($new)), 'horde.success');

        foreach ($all_folders as $folder_old) {
            $deleted[] = $folder_old;

            /* Get the new folder name. */
            $inserted[] = $folder_new = substr_replace($folder_old, $new, 0, strlen($old));
        }

        if (!empty($deleted)) {
            $imaptree->rename($deleted, $inserted);
            $this->_onDelete($deleted);
        }

        return true;
    }

    /**
     * Subscribes to one or more IMAP folders.
     *
     * @param array $folders  The folders to subscribe to (UTF7-IMAP).
     *
     * @return boolean  Were all folders successfully subscribed to?
     */
    public function subscribe($folders)
    {
        global $notification;

        $return_value = true;
        $subscribed = array();

        if (!is_array($folders)) {
            $notification->push(_("No folders were specified"), 'horde.warning');
            return false;
        }

        foreach (array_filter($folders) as $folder) {
            try {
                $GLOBALS['imp_imap']->ob()->subscribeMailbox($folder, true);
                $notification->push(sprintf(_("You were successfully subscribed to \"%s\""), IMP::displayFolder($folder)), 'horde.success');
                $subscribed[] = $folder;
            } catch (Horde_Imap_Client_Exception $e) {
                $notification->push(sprintf(_("You were not subscribed to \"%s\". Here is what the server said"), IMP::displayFolder($folder)) . ': ' . $e->getMessage(), 'horde.error');
                $return_value = false;
            }
        }

        if (!empty($subscribed)) {
            /* Initialize the IMAP_Tree object. */
            $imaptree = IMP_Imap_Tree::singleton();
            $imaptree->subscribe($subscribed);

            /* Reset the folder cache. */
            $this->clearFlistCache();
        }

        return $return_value;
    }

    /**
     * Unsubscribes from one or more IMAP folders.
     *
     * @param array $folders  The folders to unsubscribe from (UTF7-IMAP).
     *
     * @return boolean  Were all folders successfully unsubscribed from?
     */
    public function unsubscribe($folders)
    {
        global $notification;

        $return_value = true;
        $unsubscribed = array();

        if (!is_array($folders)) {
            $notification->push(_("No folders were specified"), 'horde.message');
            return false;
        }

        foreach (array_filter($folders) as $folder) {
            if (strcasecmp($folder, 'INBOX') == 0) {
                $notification->push(sprintf(_("You cannot unsubscribe from \"%s\"."), IMP::displayFolder($folder)), 'horde.error');
            } else {
                try {
                    $GLOBALS['imp_imap']->ob()->subscribeMailbox($folder, false);
                    $notification->push(sprintf(_("You were successfully unsubscribed from \"%s\""), IMP::displayFolder($folder)), 'horde.success');
                    $unsubscribed[] = $folder;
                } catch (Horde_Imap_Client_Exception $e) {
                    $notification->push(sprintf(_("You were not unsubscribed from \"%s\". Here is what the server said"), IMP::displayFolder($folder)) . ': ' . $e->getMessage(), 'horde.error');
                    $return_value = false;
                }
            }
        }

        if (!empty($unsubscribed)) {
            /* Initialize the IMAP_Tree object. */
            $imaptree = IMP_Imap_Tree::singleton();
            $imaptree->unsubscribe($unsubscribed);

            /* Reset the folder cache. */
            $this->clearFlistCache();
        }

        return $return_value;
    }

    /**
     * Generates a string that can be saved out to an mbox format mailbox file
     * for a folder or set of folders, optionally including all subfolders of
     * the selected folders as well. All folders will be put into the same
     * string.
     *
     * @author Didi Rieder <adrieder@sbox.tugraz.at>
     *
     * @param array $folder_list  A list of folder names to generate a mbox
     *                            file for (UTF7-IMAP).
     *
     * @return resource  A stream resource containing the text of a mbox
     *                   format mailbox file.
     */
    public function generateMbox($folder_list)
    {
        $body = fopen('php://temp', 'r+');

        if (empty($folder_list)) {
            return $body;
        }

        foreach ($folder_list as $folder) {
            try {
                $status = $GLOBALS['imp_imap']->ob()->status($folder, Horde_Imap_Client::STATUS_MESSAGES);
            } catch (Horde_Imap_Client_Exception $e) {
                continue;
            }
            for ($i = 1; $i <= $status['messages']; ++$i) {
                /* Download one message at a time to save on memory
                 * overhead. */
                try {
                    $res = $GLOBALS['imp_imap']->ob()->fetch($folder, array(
                            Horde_Imap_Client::FETCH_FULLMSG => array('peek' => true, 'stream' => true),
                            Horde_Imap_Client::FETCH_ENVELOPE => true,
                            Horde_Imap_Client::FETCH_DATE => true,
                        ), array('ids' => array($i), 'sequence' => true));
                    $ptr = reset($res);
                } catch (Horde_Imap_Client_Exception $e) {
                    continue;
                }

                $from = '<>';
                if (!empty($ptr['envelope']['from'])) {
                    $ptr2 = reset($ptr['envelope']['from']);
                    if (!empty($ptr2['mailbox']) && !empty($ptr2['host'])) {
                        $from = $ptr2['mailbox']. '@' . $ptr2['host'];
                    }
                }

                /* We need this long command since some MUAs (e.g. pine)
                 * require a space in front of single digit days. */
                $date = sprintf('%s %2s %s', $ptr['date']->format('D M'), $ptr['date']->format('j'), $ptr['date']->format('H:i:s Y'));
                fwrite($body, 'From ' . $from . ' ' . $date . "\r\n");
                rewind($ptr['fullmsg']);
                stream_copy_to_stream($ptr['fullmsg'], $body);
                fclose($ptr['fullmsg']);
                fwrite($body, "\r\n");
            }
        }

        return $body;
    }

    /**
     * Imports messages into a given folder from a mbox format mailbox file.
     *
     * @param string $folder  The folder to put the messages into (UTF7-IMAP).
     * @param string $mbox    String containing the mbox filename.
     *
     * @return mixed  False (boolean) on fail or the number of messages
     *                imported (integer) on success.
     */
    public function importMbox($folder, $mbox)
    {
        $message = '';
        $msgcount = 0;

        $fd = fopen($mbox, 'r');
        while (!feof($fd)) {
            $line = fgets($fd);

            if (preg_match('/From (.+@.+|- )/A', $line)) {
                if (!empty($message)) {
                    try {
                        $GLOBALS['imp_imap']->ob()->append($folder, array(array('data' => $message)));
                        ++$msgcount;
                    } catch (Horde_Imap_Client_Exception $e) {}
                }
                $message = '';
            } else {
                $message .= $line;
            }
        }
        fclose($fd);

        if (!empty($message)) {
            try {
                $GLOBALS['imp_imap']->ob()->append($folder, array(array('data' => $message)));
                ++$msgcount;
            } catch (Horde_Imap_Client_Exception $e) {}
        }

        return $msgcount ? $msgcount : false;
    }
}
