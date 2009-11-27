<?php
/**
 * The IMP_Imap_Tree class provides a tree view of the mailboxes in an
 * IMAP/POP3 repository.  It provides access functions to iterate through this
 * tree and query information about individual mailboxes.
 * In IMP, folders = IMAP mailboxes so the two terms are used interchangably.
 *
 * Copyright 2000-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jon Parise <jon@horde.org>
 * @author  Anil Madhavapeddy <avsm@horde.org>
 * @author  Michael Slusarz <slusarz@horde.org>
 * @package IMP
 */
class IMP_Imap_Tree
{
    /* Constants for mailboxElt attributes. */
    const ELT_NOSELECT = 1;
    const ELT_NAMESPACE = 2;
    const ELT_IS_OPEN = 4;
    const ELT_IS_SUBSCRIBED = 8;
    const ELT_NOSHOW = 16;
    const ELT_IS_POLLED = 32;
    const ELT_NEED_SORT = 64;
    const ELT_VFOLDER = 128;
    const ELT_NONIMAP = 256;
    const ELT_INVISIBLE = 512;

    /* The isOpen() expanded mode constants. */
    const OPEN_NONE = 0;
    const OPEN_ALL = 1;
    const OPEN_USER = 2;

    /* The manner to which to traverse the tree when calling next(). */
    const NEXT_SHOWCLOSED = 1;
    const NEXT_SHOWSUB = 2;

    /* The string used to indicate the base of the tree. This must be null
     * since this is the only 7-bit character not allowed in IMAP
     * mailboxes. */
    const BASE_ELT = '\0';

    /* Defines used with folderList(). */
    const FLIST_CONTAINER = 1;
    const FLIST_UNSUB = 2;
    const FLIST_VFOLDER = 4;
    const FLIST_OB = 8;
    const FLIST_ELT = 16;

    /* Add null to folder key since it allows us to sort by name but
     * never conflict with an IMAP mailbox. */
    const VFOLDER_KEY = 'vfolder\0';

    /* Defines used with namespace display. */
    const SHARED_KEY = 'shared\0';
    const OTHER_KEY = 'other\0';

    /**
     * Singleton instance
     *
     * @var IMP_Imap_Tree
     */
    static protected $_instance;

    /**
     * Array containing the mailbox tree.
     *
     * @var array
     */
    protected $_tree;

    /**
     * Location of current element in the tree.
     *
     * @var string
     */
    protected $_currparent = null;

    /**
     * Location of current element in the tree.
     *
     * @var integer
     */
    protected $_currkey = null;

    /**
     * Location of current element in the tree.
     *
     * @var array
     */
    protected $_currstack = array();

    /**
     * Show unsubscribed mailboxes?
     *
     * @var boolean
     */
    protected $_showunsub = false;

    /**
     * Parent list.
     *
     * @var array
     */
    protected $_parent = array();

    /**
     * The cached list of mailboxes to poll.
     *
     * @var array
     */
    protected $_poll = null;

    /**
     * The cached list of expanded folders.
     *
     * @var array
     */
    protected $_expanded = null;

    /**
     * Cached list of subscribed mailboxes.
     *
     * @var array
     */
    protected $_subscribed = null;

    /**
     * The cached full list of mailboxes on the server.
     *
     * @var array
     */
    protected $_fulllist = null;

    /**
     * Tree changed flag.  Set when something in the tree has been altered.
     *
     * @var boolean
     */
    protected $_changed = false;

    /**
     * Have we shown unsubscribed folders previously?
     *
     * @var boolean
     */
    protected $_unsubview = false;

    /**
     * The string used for the IMAP delimiter.
     *
     * @var string
     */
    protected $_delimiter = '/';

    /**
     * The list of namespaces to add to the tree.
     *
     * @var array
     */
    protected $_namespaces = array();

    /**
     * Used to determine the list of element changes.
     *
     * @var array
     */
    protected $_eltdiff = null;

    /**
     * If set, track element changes.
     *
     * @var boolean
     */
    protected $_trackdiff = true;

    /**
     * See $open parameter in build().
     *
     * @var boolean
     */
    protected $_forceopen = false;

    /**
     * Mailbox icons cache.
     *
     * @var array
     */
    protected $_mboxIcons;

    /**
     * Element cache for element().
     *
     * @var array
     */
    protected $_eltCache;

    /**
     * Attempts to return a reference to a concrete IMP_Imap_Tree instance.
     *
     * If an IMP_Imap_Tree object is currently stored in the cache, re-create
     * that object.  Else, create a new instance.  Ensures that only one
     * instance is available at any time.
     *
     * @return IMP_Imap_Tree  The object or null.
     */
    static public function singleton()
    {
        if (!isset(self::$_instance)) {
            if (!empty($_SESSION['imp']['cache']['tree'])) {
                $imp_cache = IMP::getCache();
                self::$_instance = @unserialize($imp_cache->get($_SESSION['imp']['cache']['tree'], 86400));
            }

            if (empty(self::$_instance)) {
                self::$_instance = new IMP_Imap_Tree();
            }
        }

        return self::$_instance;
    }

    /**
     * Constructor.
     */
    protected function __construct()
    {
        if ($_SESSION['imp']['protocol'] == 'imap') {
            $ns = $GLOBALS['imp_imap']->getNamespaceList();
            $ptr = reset($ns);
            $this->_delimiter = $ptr['delimiter'];
            $this->_namespaces = empty($GLOBALS['conf']['user']['allow_folders'])
                ? array()
                : $ns;
        }

        if ($imp_cache = IMP::getCache()) {
            $_SESSION['imp']['cache']['tree'] = uniqid(mt_rand() . Horde_Auth::getAuth());
        }

        $this->init();
        $this->__wakeup();
    }

    /**
     * Do cleanup prior to serialization and provide a list of variables
     * to serialize.
     */
    public function __sleep()
    {
        return array('_tree', '_showunsub', '_parent', '_unsubview', '_delimiter', '_namespaces');
    }

    /**
     * Tasks to do on wakeup.
     */
    public function __wakeup()
    {
        register_shutdown_function(array($this, 'shutdown'));
    }

    /**
     * Store a serialized version of ourself in the current session.
     */
    public function shutdown()
    {
        /* We only need to store the object if using Horde_Cache and the tree
         * has changed. */
        if (!empty($this->_changed) &&
            isset($_SESSION['imp']['cache']['tree'])) {
            $imp_cache = IMP::getCache();
            $imp_cache->set($_SESSION['imp']['cache']['tree'], serialize($this), 86400);
        }
    }

    /**
     * Returns the list of mailboxes on the server.
     *
     * @param boolean $showunsub  Show unsubscribed mailboxes?
     *
     * @return array  A list of mailbox names.
     */
    protected function _getList($showunsub)
    {
        if ($showunsub && !is_null($this->_fulllist)) {
            return array_keys($this->_fulllist);
        } elseif (!$showunsub && !is_null($this->_subscribed)) {
            return array_keys($this->_subscribed);
        }

        /* INBOX must always appear. */
        $names = array('INBOX');

        foreach ($this->_namespaces as $key => $val) {
            try {
                $names = array_merge($names, $GLOBALS['imp_imap']->ob()->listMailboxes($key . '*', $showunsub ? Horde_Imap_Client::MBOX_ALL : Horde_Imap_Client::MBOX_SUBSCRIBED_EXISTS, array('flat' => true)));
            } catch (Horde_Imap_Client_Exception $e) {}
        }

        if ($showunsub) {
            $this->_fulllist = array_flip($names);
        } else {
            $this->_subscribed = array_flip($names);
        }

        return $names;
    }

    /**
     * Make a single mailbox tree element.
     * An element consists of the following items (we use single letters here
     * to save in session storage space):
     *   'a'  --  Attributes
     *   'c'  --  Level count
     *   'l'  --  Label
     *   'p'  --  Parent node
     *   'v'  --  Value
     *
     * @param string $name         The mailbox name.
     * @param integer $attributes  The mailbox's attributes.
     *
     * @return array  See above format.
     * @throws Horde_Exception
     */
    protected function _makeElt($name, $attributes = 0)
    {
        $elt = array(
            'a' => $attributes,
            'c' => 0,
            'p' => self::BASE_ELT,
            'v' => $name
        );

        /* Set subscribed values. We know the folder is subscribed, without
         * query of the IMAP server, in the following situations:
         * + Folder is INBOX.
         * + We are adding while in subscribe-only mode.
         * + Subscriptions are turned off. */
        if (!$this->isSubscribed($elt)) {
            if (!$this->_showunsub ||
                ($elt['v'] == 'INBOX') ||
                !$GLOBALS['prefs']->getValue('subscribe')) {
                $this->_setSubscribed($elt, true);
            } else {
                $this->_initSubscribed();
                $this->_setSubscribed($elt, isset($this->_subscribed[$elt['v']]));
            }
        }

        /* Check for polled status. */
        $this->_initPollList();
        $this->_setPolled($elt, isset($this->_poll[$elt['v']]));

        /* Check for open status. */
        switch ($GLOBALS['prefs']->getValue('nav_expanded')) {
        case self::OPEN_NONE:
            $open = false;
            break;

        case self::OPEN_ALL:
            $open = true;
            break;

        case self::OPEN_USER:
            $this->_initExpandedList();
            $open = !empty($this->_expanded[$elt['v']]);
            break;
        }
        $this->_setOpen($elt, $open);

        /* Computed values. */
        $ns_info = $this->_getNamespace($elt['v']);
        $tmp = explode(is_null($ns_info) ? $this->_delimiter : $ns_info['delimiter'], $elt['v']);
        $elt['c'] = count($tmp) - 1;

        /* Get the mailbox label. */
        $elt['l'] = IMP::getLabel($tmp[$elt['c']]);

        if ($_SESSION['imp']['protocol'] != 'pop') {
            try {
                $this->_setInvisible($elt, !Horde::callHook('display_folder', array($elt['v']), 'imp'));
            } catch (Horde_Exception_HookNotSet $e) {}

            if ($elt['c'] != 0) {
                $elt['p'] = implode(is_null($ns_info) ? $this->_delimiter : $ns_info['delimiter'], array_slice($tmp, 0, $elt['c']));
            }

            if (!is_null($ns_info)) {
                switch ($ns_info['type']) {
                case 'personal':
                    /* Strip personal namespace. */
                    if (!empty($ns_info['name']) && ($elt['c'] != 0)) {
                        --$elt['c'];
                        if (strpos($elt['p'], $ns_info['delimiter']) === false) {
                            $elt['p'] = self::BASE_ELT;
                        } elseif (strpos($elt['v'], $ns_info['name'] . 'INBOX' . $ns_info['delimiter']) === 0) {
                            $elt['p'] = 'INBOX';
                        }
                    }
                    break;

                case 'other':
                case 'shared':
                    if (substr($ns_info['name'], 0, -1 * strlen($ns_info['delimiter'])) == $elt['v']) {
                        $elt['a'] = self::ELT_NOSELECT | self::ELT_NAMESPACE;
                    }

                    if ($GLOBALS['prefs']->getValue('tree_view')) {
                        $name = ($ns_info['type'] == 'other') ? self::OTHER_KEY : self::SHARED_KEY;
                        if ($elt['c'] == 0) {
                            $elt['p'] = $name;
                            ++$elt['c'];
                        } elseif ($this->_tree[$name] && self::ELT_NOSHOW) {
                            if ($elt['c'] == 1) {
                                $elt['p'] = $name;
                            }
                        } else {
                            ++$elt['c'];
                        }
                    }
                    break;
                }
            }
        }

        return $elt;
    }

    /**
     * Initalize the tree.
     */
    public function init()
    {
        $unsubmode = (($_SESSION['imp']['protocol'] == 'pop') ||
                      !$GLOBALS['prefs']->getValue('subscribe') ||
                      $_SESSION['imp']['showunsub']);

        /* Reset class variables to the defaults. */
        $this->_changed = true;
        $this->_currkey = $this->_currparent = $this->_subscribed = null;
        $this->_currstack = $this->_tree = $this->_parent = array();
        $this->_showunsub = $this->_unsubview = $unsubmode;

        /* Create a placeholder element to the base of the tree list so we can
         * keep track of whether the base level needs to be sorted. */
        $this->_tree[self::BASE_ELT] = array(
            'a' => self::ELT_NEED_SORT,
            'v' => self::BASE_ELT
        );

        /* Add INBOX and exit if folders aren't allowed or if we are using
         * POP3. */
        if (empty($GLOBALS['conf']['user']['allow_folders']) ||
            ($_SESSION['imp']['protocol'] == 'pop')) {
            $this->_insertElt($this->_makeElt('INBOX', self::ELT_IS_SUBSCRIBED));
            return;
        }

        /* Add namespace elements. */
        foreach ($this->_namespaces as $key => $val) {
            if ($val['type'] != 'personal' &&
                $GLOBALS['prefs']->getValue('tree_view')) {
                $elt = $this->_makeElt(
                    ($val['type'] == 'other') ? self::OTHER_KEY : self::SHARED_KEY,
                    self::ELT_NOSELECT | self::ELT_NAMESPACE | self::ELT_NONIMAP | self::ELT_NOSHOW
                );
                $elt['l'] = ($val['type'] == 'other')
                    ? _("Other Users' Folders")
                    : _("Shared Folders");

                foreach ($this->_namespaces as $val2) {
                    if (($val2['type'] == $val['type']) &&
                        ($val2['name'] != $val['name'])) {
                        $elt['a'] &= ~self::ELT_NOSHOW;
                        break;
                    }
                }

                $this->_insertElt($elt);
            }
        }

        /* Create the list (INBOX and all other hierarchies). */
        $this->insert($this->_getList($this->_showunsub));

        /* Add virtual folders to the tree. */
        $this->insertVFolders($GLOBALS['imp_search']->listQueries(IMP_Search::LIST_VFOLDER));
    }

    /**
     * Expand a mail folder.
     *
     * @param string $folder      The folder name to expand.
     * @param boolean $expandall  Expand all folders under this one?
     */
    public function expand($folder, $expandall = false)
    {
        $folder = $this->_convertName($folder);

        if (!isset($this->_tree[$folder])) {
            return;
        }
        $elt = &$this->_tree[$folder];

        if ($this->hasChildren($elt)) {
            if (!$this->isOpen($elt)) {
                $this->_changed = true;
                $this->_setOpen($elt, true);
            }

            /* Expand all children beneath this one. */
            if ($expandall && !empty($this->_parent[$folder])) {
                foreach ($this->_parent[$folder] as $val) {
                    $this->expand($this->_tree[$val]['v'], true);
                }
            }
        }
    }

    /**
     * Collapse a mail folder.
     *
     * @param string $folder  The folder name to collapse.
     */
    public function collapse($folder)
    {
        $folder = $this->_convertName($folder);

        if (isset($this->_tree[$folder]) &&
            $this->isOpen($this->_tree[$folder])) {
            $this->_changed = true;
            $this->_setOpen($this->_tree[$folder], false);
        }
    }

    /**
     * Sets the internal array pointer to the next element, and returns the
     * next object.
     *
     * @param integer $mask  A mask with the following elements:
     * <pre>
     * IMP_Imap_Tree::NEXT_SHOWCLOSED - Don't ignore closed elements.
     * IMP_Imap_Tree::NEXT_SHOWSUB - Only show subscribed elements.
     * </pre>
     *
     * @return mixed  Returns the next element or false if the element doesn't
     *                exist.
     */
    public function next($mask = 0)
    {
        if (is_null($this->_currkey) && is_null($this->_currparent)) {
            return false;
        }

        $curr = $this->current();

        $old_showunsub = $this->_showunsub;
        if ($mask & self::NEXT_SHOWSUB) {
            $this->_showunsub = false;
        }

        if ($this->_activeElt($curr) &&
            (($mask & self::NEXT_SHOWCLOSED) || $this->isOpen($curr)) &&
            ($this->_currparent != $curr['v'])) {
            /* If the current element is open, and children exist, move into
             * it. */
            $this->_currstack[] = array(
                'k' => $this->_currkey,
                'p' => $this->_currparent
            );
            $this->_currkey = 0;
            $this->_currparent = $curr['v'];
            $this->_sortLevel($curr['v']);

            $curr = $this->current();
            if ($GLOBALS['prefs']->getValue('tree_view') &&
                $this->isNamespace($curr) &&
                !$this->_isNonIMAPElt($curr) &&
                ($this->_tree[$curr['p']] && self::ELT_NOSHOW)) {
                $this->next($mask);
            }
        } else {
            /* Else, increment within the current subfolder. */
            ++$this->_currkey;
        }

        $curr = $this->current();
        if (!$curr) {
            if (empty($this->_currstack)) {
                $this->_currkey = $this->_currparent = null;
                $this->_showunsub = $old_showunsub;
                return false;
            }

            do {
                $old = array_pop($this->_currstack);
                $this->_currkey = $old['k'] + 1;
                $this->_currparent = $old['p'];
            } while ((($curr = $this->current()) == false) &&
                     !empty($this->_currstack));
        }

        $res = $this->_activeElt($curr);
        $this->_showunsub = $old_showunsub;

        return $res ? $curr : $this->next($mask);
    }

    /**
     * Set internal pointer to the head of the tree.
     * This MUST be called before you can traverse the tree with next().
     *
     * @return mixed  Returns the element at the head of the tree or false
     *                if the element doesn't exist.
     */
    public function reset()
    {
        $this->_currkey = 0;
        $this->_currparent = self::BASE_ELT;
        $this->_currstack = array();
        $this->_sortLevel($this->_currparent);
        return $this->current();
    }

    /**
     * Return the current tree element.
     *
     * @return array  The current tree element or false if there is no
     *                element.
     */
    public function current()
    {
        return isset($this->_parent[$this->_currparent][$this->_currkey])
            ? $this->_tree[$this->_parent[$this->_currparent][$this->_currkey]]
            : false;
    }

    /**
     * Determines if there are more elements in the current tree level.
     *
     * @return boolean  True if there are more elements, false if this is the
     *                  last element.
     */
    public function peek()
    {
        for ($i = ($this->_currkey + 1);; ++$i) {
            if (!isset($this->_parent[$this->_currparent][$i])) {
                return false;
            }
            if ($this->_activeElt($this->_tree[$this->_parent[$this->_currparent][$i]])) {
                return true;
            }
        }
    }

    /**
     * Returns the requested element.
     *
     * @param string $name  The name of the tree element.
     *
     * @return array  Returns the requested element or false if not found.
     */
    public function get($name)
    {
        $name = $this->_convertName($name);
        return isset($this->_tree[$name]) ? $this->_tree[$name] : false;
    }

    /**
     * Insert a folder/mailbox into the tree.
     *
     * @param mixed $id  The name of the folder (or a list of folder names)
     *                   to add.
     */
    public function insert($id)
    {
        if (is_array($id)) {
            /* We want to add from the BASE of the tree up for efficiency
             * sake. */
            $this->_sortList($id);
        } else {
            $id = array($id);
        }

        foreach ($id as $val) {
            if (isset($this->_tree[$val]) &&
                !$this->isContainer($this->_tree[$val])) {
                continue;
            }

            $ns_info = $this->_getNamespace($val);
            if (is_null($ns_info)) {
                if (strpos($val, self::VFOLDER_KEY . $this->_delimiter) === 0) {
                    $elt = $this->_makeElt(self::VFOLDER_KEY, self::ELT_VFOLDER | self::ELT_NOSELECT | self::ELT_NONIMAP);
                    $elt['l'] = _("Virtual Folders");
                    $this->_insertElt($elt);
                }

                $elt = $this->_makeElt($val, self::ELT_VFOLDER | self::ELT_IS_SUBSCRIBED);
                $elt['l'] = $elt['v'] = Horde_String::substr($val, Horde_String::length(self::VFOLDER_KEY) + Horde_String::length($this->_delimiter));
                $this->_insertElt($elt);
            } else {
                /* Break apart the name via the delimiter and go step by
                 * step through the name to make sure all subfolders exist
                 * in the tree. */
                $parts = explode($ns_info['delimiter'], $val);
                $parts[0] = $this->_convertName($parts[0]);
                $parts_count = count($parts);
                for ($i = 0; $i < $parts_count; ++$i) {
                    $part = implode($ns_info['delimiter'], array_slice($parts, 0, $i + 1));

                    if (isset($this->_tree[$part])) {
                        if (($part == $val) &&
                            $this->isContainer($this->_tree[$part])) {
                            $this->_setContainer($this->_tree[$part], false);
                        }
                    } else {
                        $this->_insertElt(($part == $val) ? $this->_makeElt($part) : $this->_makeElt($part, self::ELT_NOSELECT));
                    }
                }
            }
        }
    }

    /**
     * Insert an element into the tree.
     *
     * @param array $elt  The element to insert. The key in the tree is the
     *                    'v' (value) element of the element.
     */
    protected function _insertElt($elt)
    {
        if (!strlen($elt['l']) || isset($this->_tree[$elt['v']])) {
            return;
        }

        // UW fix - it may return both 'foo' and 'foo/' as folder names.
        // Only add one of these (without the namespace character) to
        // the tree.  See Ticket #5764.
        $ns_info = $this->_getNamespace($elt['v']);
        if (isset($this->_tree[rtrim($elt['v'], is_null($ns_info) ? $this->_delimiter : $ns_info['delimiter'])])) {
            return;
        }

        $this->_changed = true;

        /* Set the parent array to the value in $elt['p']. */
        if (empty($this->_parent[$elt['p']])) {
            $this->_parent[$elt['p']] = array();
            // This is a case where it is possible that the parent element has
            // changed (it now has children) but we can't catch it via the
            // bitflag (since hasChildren() is dynamically determined).
            if ($this->_trackdiff &&
                !is_null($this->_eltdiff) &&
                !isset($this->_eltdiff['a'][$elt['p']])) {
                $this->_eltdiff['c'][$elt['p']] = 1;
            }
        }
        $this->_parent[$elt['p']][] = $elt['v'];
        $this->_tree[$elt['v']] = $elt;

        if ($this->_trackdiff && !is_null($this->_eltdiff)) {
            $this->_eltdiff['a'][$elt['v']] = 1;
        }

        /* Make sure we are sorted correctly. */
        if (count($this->_parent[$elt['p']]) > 1) {
            $this->_setNeedSort($this->_tree[$elt['p']], true);
        }
    }

    /**
     * Delete an element from the tree.
     *
     * @param mixed $id  The element name or an array of element names.
     *
     * @return boolean  Return true on success, false on error.
     */
    public function delete($id)
    {
        if (is_array($id)) {
            /* We want to delete from the TOP of the tree down to ensure that
             * parents have an accurate view of what children are left. */
            $this->_sortList($id);
            $id = array_reverse($id);

            foreach ($id as $val) {
                $currsuccess = $this->delete($val);
                if (!$currsuccess) {
                    return false;
                }
            }

            return true;
        }

        $id = $this->_convertName($id, true);
        $vfolder_base = ($id == self::VFOLDER_KEY);
        $search_id = $GLOBALS['imp_search']->createSearchID($id);

        if ($vfolder_base ||
            (isset($this->_tree[$search_id]) &&
             $this->isVFolder($this->_tree[$search_id]))) {
            if (!$vfolder_base) {
                $id = $search_id;
            }

            $parent = $this->_tree[$id]['p'];
            unset($this->_tree[$id]);

            if (!is_null($this->_eltdiff)) {
                $this->_eltdiff['d'][$id] = 1;
            }

            /* Delete the entry from the parent tree. */
            $key = array_search($id, $this->_parent[$parent]);
            unset($this->_parent[$parent][$key]);

            /* Rebuild the parent tree. */
            if (!$vfolder_base && empty($this->_parent[$parent])) {
                $this->delete($parent);
            } else {
                $this->_parent[$parent] = array_values($this->_parent[$parent]);
            }
            $this->_changed = true;

            return true;
        }

        $ns_info = $this->_getNamespace($id);

        if (($id == 'INBOX') ||
            !isset($this->_tree[$id]) ||
            ($id == $ns_info['name'])) {
            return false;
        }

        $this->_changed = true;

        $elt = &$this->_tree[$id];

        /* Delete the entry from the folder list cache(s). */
        foreach (array('_subscribed', '_fulllist') as $var) {
            if (!is_null($this->$var)) {
                unset($this->$var[$id]);
            }
        }

        /* Do not delete from tree if there are child elements - instead,
         * convert to a container element. */
        if ($this->hasChildren($elt)) {
            $this->_setContainer($elt, true);
            return true;
        }

        $parent = $elt['p'];

        /* Delete the tree entry. */
        unset($this->_tree[$id]);

        /* Delete the entry from the parent tree. */
        $key = array_search($id, $this->_parent[$parent]);
        unset($this->_parent[$parent][$key]);

        if (!is_null($this->_eltdiff)) {
            $this->_eltdiff['d'][$id] = 1;
        }

        if (empty($this->_parent[$parent])) {
            /* This folder is now completely empty (no children).  If the
             * folder is a container only, we should delete the folder from
             * the tree. */
            unset($this->_parent[$parent]);
            if (isset($this->_tree[$parent])) {
                if ($this->isContainer($this->_tree[$parent]) &&
                    !$this->isNamespace($this->_tree[$parent])) {
                    $this->delete($parent);
                } else {
                    $this->_modifyExpandedList($parent, 'remove');
                    $this->_setOpen($this->_tree[$parent], false);
                    /* This is a case where it is possible that the parent
                     * element has changed (it no longer has children) but
                     * we can't catch it via the bitflag (since hasChildren()
                     * is dynamically determined). */
                    if (!is_null($this->_eltdiff)) {
                        $this->_eltdiff['c'][$parent] = 1;
                    }
                }
            }
        } else {
            /* Rebuild the parent tree. */
            $this->_parent[$parent] = array_values($this->_parent[$parent]);
        }

        /* Remove the mailbox from the expanded folders list. */
        $this->_modifyExpandedList($id, 'remove');

        /* Remove the mailbox from the nav_poll list. */
        $this->removePollList($id);

        return true;
    }

    /**
     * Subscribe an element to the tree.
     *
     * @param mixed $id  The element name or an array of element names.
     */
    public function subscribe($id)
    {
        if (!is_array($id)) {
            $id = array($id);
        }

        foreach ($id as $val) {
            $val = $this->_convertName($val);
            if (isset($this->_tree[$val])) {
                $this->_changed = true;
                $this->_setSubscribed($this->_tree[$val], true);
                $this->_setContainer($this->_tree[$val], false);
            }
        }
    }

    /**
     * Unsubscribe an element from the tree.
     *
     * @param mixed $id  The element name or an array of element names.
     */
    public function unsubscribe($id)
    {
        if (!is_array($id)) {
            $id = array($id);
        } else {
            /* We want to delete from the TOP of the tree down to ensure that
             * parents have an accurate view of what children are left. */
            $this->_sortList($id);
            $id = array_reverse($id);
        }

        foreach ($id as $val) {
            $val = $this->_convertName($val);

            /* INBOX can never be unsubscribed to. */
            if (isset($this->_tree[$val]) && ($val != 'INBOX')) {
                $this->_changed = $this->_unsubview = true;

                $elt = &$this->_tree[$val];

                /* Do not delete from tree if there are child elements -
                 * instead, convert to a container element. */
                if (!$this->_showunsub && $this->hasChildren($elt)) {
                    $this->_setContainer($elt, true);
                }

                /* Set as unsubscribed, add to unsubscribed list, and remove
                 * from subscribed list. */
                $this->_setSubscribed($elt, false);
            }
        }
    }

    /**
     * Set an attribute for an element.
     *
     * @param array &$elt     The tree element.
     * @param integer $const  The constant to set/remove from the bitmask.
     * @param boolean $bool   Should the attribute be set?
     */
    protected function _setAttribute(&$elt, $const, $bool)
    {
        if ($bool) {
            $elt['a'] |= $const;
        } else {
            $elt['a'] &= ~$const;
        }
    }

    /**
     * Does the element have any active children?
     *
     * @param array $elt  A tree element.
     *
     * @return boolean  True if the element has active children.
     */
    public function hasChildren($elt)
    {
        if (isset($this->_parent[$elt['v']])) {
            if ($this->_showunsub) {
                return true;
            }

            foreach ($this->_parent[$elt['v']] as $val) {
                if ($this->isSubscribed($this->_tree[$val]) ||
                    $this->hasChildren($this->_tree[$val])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Is the tree element open?
     *
     * @param array $elt  A tree element.
     *
     * @return integer  True if the element is open.
     */
    public function isOpen($elt)
    {
        return (($elt['a'] & self::ELT_IS_OPEN) && $this->hasChildren($elt));
    }

    /**
     * Set the open attribute for an element.
     *
     * @param array &$elt    A tree element.
     * @param boolean $bool  The setting.
     */
    protected function _setOpen(&$elt, $bool)
    {
        $this->_setAttribute($elt, self::ELT_IS_OPEN, $bool);
        $this->_modifyExpandedList($elt['v'], $bool ? 'add' : 'remove');
    }

    /**
     * Is this element a container only, not a mailbox (meaning you can
     * not open it)?
     *
     * @param array $elt  A tree element.
     *
     * @return integer  True if the element is a container.
     */
    public function isContainer($elt)
    {
        return (($elt['a'] & self::ELT_NOSELECT) ||
                (!$this->_showunsub &&
                 !$this->isSubscribed($elt) &&
                 $this->hasChildren($elt)));
    }

    /**
     * Set the element as a container?
     *
     * @param array &$elt    A tree element.
     * @param boolean $bool  Is the element a container?
     */
    protected function _setContainer(&$elt, $bool)
    {
        $this->_setAttribute($elt, self::ELT_NOSELECT, $bool);
    }

    /**
     * Is the user subscribed to this element?
     *
     * @param array $elt  A tree element.
     *
     * @return integer  True if the user is subscribed to the element.
     */
    public function isSubscribed($elt)
    {
        return $elt['a'] & self::ELT_IS_SUBSCRIBED;
    }

    /**
     * Set the subscription status for an element.
     *
     * @param array &$elt    A tree element.
     * @param boolean $bool  Is the element subscribed to?
     */
    protected function _setSubscribed(&$elt, $bool)
    {
        $this->_setAttribute($elt, self::ELT_IS_SUBSCRIBED, $bool);
        if (!is_null($this->_subscribed)) {
            if ($bool) {
                $this->_subscribed[$elt['v']] = 1;
            } else {
                unset($this->_subscribed[$elt['v']]);
            }
        }
    }

    /**
     * Is the element a namespace container?
     *
     * @param array $elt  A tree element.
     *
     * @return integer  True if the element is a namespace container.
     */
    public function isNamespace($elt)
    {
        return $elt['a'] & self::ELT_NAMESPACE;
    }

    /**
     * Is the element a non-IMAP element?
     *
     * @param array $elt  A tree element.
     *
     * @return integer  True if the element is a non-IMAP element.
     */
    protected function _isNonIMAPElt($elt)
    {
        return $elt['a'] & self::ELT_NONIMAP;
    }

    /**
     * Initialize the expanded folder list.
     */
    protected function _initExpandedList()
    {
        if (is_null($this->_expanded)) {
            $serialized = $GLOBALS['prefs']->getValue('expanded_folders');
            $this->_expanded = $serialized
                ? unserialize($serialized)
                : array();
        }
    }

    /**
     * Add/remove an element to the expanded list.
     *
     * @param string $id      The element name to add/remove.
     * @param string $action  Either 'add' or 'remove';
     */
    protected function _modifyExpandedList($id, $action)
    {
        $this->_initExpandedList();
        if ($action == 'add') {
            $this->_expanded[$id] = true;
        } else {
            unset($this->_expanded[$id]);
        }
        $GLOBALS['prefs']->setValue('expanded_folders', serialize($this->_expanded));
    }

    /**
     * Initializes and returns the list of mailboxes to poll.
     *
     * @param boolean $sort   Sort the directory list?
     * @param boolean $prune  Prune non-existent folders from list?
     *
     * @return array  The list of mailboxes to poll.
     */
    public function getPollList($sort = false, $prune = false)
    {
        $this->_initPollList();

        $plist = $prune
            ? array_values(array_intersect(array_keys($this->_poll), $this->folderList()))
            : array_keys($this->_poll);

        if ($sort) {
            $ns_new = $this->_getNamespace(null);
            Horde_Imap_Client_Sort::sortMailboxes($plist, array('delimiter' => $ns_new['delimiter'], 'inbox' => true));
        }

        return array_filter($plist);
    }

    /**
     * Init the poll list.  Called once per session.
     */
    protected function _initPollList()
    {
        if (is_null($this->_poll)) {
            /* We ALWAYS poll the INBOX. */
            $this->_poll = array('INBOX' => 1);

            /* Add the list of polled mailboxes from the prefs. */
            if ($GLOBALS['prefs']->getValue('nav_poll_all')) {
                $navPollList = $this->_getList(true);
            } else {
                $navPollList = @unserialize($GLOBALS['prefs']->getValue('nav_poll'));
            }

            if ($navPollList) {
                $this->_poll += $navPollList;
            }
        }
    }

    /**
     * Add element to the poll list.
     *
     * @param mixed $id  The element name or a list of element names to add.
     */
    public function addPollList($id)
    {
        if (!is_array($id)) {
            $id = array($id);
        }

        if (empty($id) || $GLOBALS['prefs']->isLocked('nav_poll')) {
            return;
        }

        $imp_folder = IMP_Folder::singleton();
        $this->getPollList();
        foreach ($id as $val) {
            if (!$this->isSubscribed($this->_tree[$val])) {
                $imp_folder->subscribe(array($val));
            }
            $this->_poll[$val] = true;
            $this->_setPolled($this->_tree[$val], true);
        }
        $GLOBALS['prefs']->setValue('nav_poll', serialize($this->_poll));
        $this->_changed = true;
    }

    /**
     * Remove element from the poll list.
     *
     * @param string $id  The folder/mailbox or a list of folders/mailboxes
     *                    to remove.
     */
    public function removePollList($id)
    {
        if ($GLOBALS['prefs']->isLocked('nav_poll')) {
            return;
        }

        if (!is_array($id)) {
            $id = array($id);
        }

        $removed = false;

        $this->getPollList();
        foreach ($id as $val) {
            if ($val != 'INBOX') {
                unset($this->_poll[$val]);
                if (isset($this->_tree[$val])) {
                    $this->_setPolled($this->_tree[$val], false);
                }
                $removed = true;
            }
        }

        if ($removed) {
            $GLOBALS['prefs']->setValue('nav_poll', serialize($this->_poll));
            $this->_changed = true;
        }
    }

    /**
     * Does the user want to poll this mailbox for new/unseen messages?
     *
     * @param array $elt  A tree element.
     *
     * @return integer  True if the user wants to poll the element.
     */
    public function isPolled($elt)
    {
        return $GLOBALS['prefs']->getValue('nav_poll_all')
            ? true
            : ($elt['a'] & self::ELT_IS_POLLED);
    }

    /**
     * Set the polled attribute for an element.
     *
     * @param array &$elt    A tree element.
     * @param boolean $bool  The setting.
     */
    protected function _setPolled(&$elt, $bool)
    {
        $this->_setAttribute($elt, self::ELT_IS_POLLED, $bool);
    }

    /**
     * Is the element invisible?
     *
     * @param array $elt  A tree element.
     *
     * @return integer  True if the element is marked as invisible.
     */
    public function isInvisible($elt)
    {
        return $elt['a'] & self::ELT_INVISIBLE;
    }

    /**
     * Set the invisible attribute for an element.
     *
     * @param array &$elt    A tree element.
     * @param boolean $bool  The setting.
     */
    protected function _setInvisible(&$elt, $bool)
    {
        $this->_setAttribute($elt, self::ELT_INVISIBLE, $bool);
    }

    /**
     * Flag the element as needing its children to be sorted.
     *
     * @param array &$elt    A tree element.
     * @param boolean $bool  The setting.
     */
    protected function _setNeedSort(&$elt, $bool)
    {
        $this->_setAttribute($elt, self::ELT_NEED_SORT, $bool);
    }

    /**
     * Does this element's children need sorting?
     *
     * @param array $elt  A tree element.
     *
     * @return integer  True if the children need to be sorted.
     */
    protected function _needSort($elt)
    {
        return (($elt['a'] & self::ELT_NEED_SORT) && (count($this->_parent[$elt['v']]) > 1));
    }

    /**
     * Initialize the list of subscribed mailboxes.
     */
    protected function _initSubscribed()
    {
        if (is_null($this->_subscribed)) {
            $this->_getList(false);
        }
    }

    /**
     * Should we expand all elements?
     */
    public function expandAll()
    {
        foreach ($this->_parent[self::BASE_ELT] as $val) {
            $this->expand($val, true);
        }
    }

    /**
     * Should we collapse all elements?
     */
    public function collapseAll()
    {
        foreach ($this->_tree as $key => $val) {
            if ($key !== self::BASE_ELT) {
                $this->collapse($val['v']);
            }
        }
    }

    /**
     * Switch subscribed/unsubscribed viewing.
     *
     * @param boolean $unsub  Show unsubscribed elements?
     */
    public function showUnsubscribed($unsub)
    {
        if ((bool)$unsub === $this->_showunsub) {
            return;
        }

        $this->_showunsub = $unsub;
        $this->_changed = true;

        /* If we are switching from unsubscribed to subscribed, no need
         * to do anything (we just ignore unsubscribed stuff). */
        if ($unsub === false) {
            return;
        }

        /* If we are switching from subscribed to unsubscribed, we need
         * to add all unsubscribed elements that live in currently
         * discovered items. */
        $this->_unsubview = true;
        $this->_trackdiff = false;
        $this->insert($this->_getList(true));
        $this->_trackdiff = true;
    }

    /**
     * Get information about new/unseen/total messages for the given element.
     *
     * @param string $name  The element name (UTF7-IMAP).
     *
     * @return array  Array with the following fields:
     * <pre>
     * 'messages' - (integer) Number of total messages.
     * 'recent' - (integer) Number of new messages.
     * 'unseen' - (integer) Number of unseen messages.
     * </pre>
     */
    public function getElementInfo($name)
    {
        try {
            return $GLOBALS['imp_imap']->ob()->status($name, Horde_Imap_Client::STATUS_MESSAGES | Horde_Imap_Client::STATUS_RECENT | Horde_Imap_Client::STATUS_UNSEEN);
        } catch (Horde_Imap_Client_Exception $e) {
            return array();
        }
    }

    /**
     * Sorts a list of mailboxes.
     *
     * @param array &$mbox   The list of mailboxes to sort.
     * @param boolean $base  Are we sorting a list of mailboxes in the base
     *                       of the tree.
     */
    protected function _sortList(&$mbox, $base = false)
    {
        $config_array = array(
            'delimiter' => $this->_delimiter,
            'inbox' => true
        );

        if (!$base) {
            Horde_Imap_Client_Sort::sortMailboxes($mbox, $config_array);
            return;
        }

        $basesort = array();
        foreach ($mbox as $val) {
            $basesort[$val] = ($val == 'INBOX') ? 'INBOX' : $this->_tree[$val]['l'];
        }
        $config_array['index'] = true;
        Horde_Imap_Client_Sort::sortMailboxes($basesort, $config_array);
        $mbox = array_keys($basesort);

        for ($i = 0, $count = count($mbox); $i < $count; ++$i) {
            if ($this->_isNonIMAPElt($this->_tree[$mbox[$i]])) {
                /* Already sorted by name - simply move to the end of
                 * the array. */
                $mbox[] = $mbox[$i];
                unset($mbox[$i]);
            }
        }
        $mbox = array_values($mbox);
    }

    /**
     * Is the given element an "active" element (i.e. an element that should
     * be worked with given the current viewing parameters).
     *
     * @param array $elt  A tree element.
     *
     * @return boolean  True if it is an active element.
     */
    protected function _activeElt($elt)
    {
        return (!$this->isInvisible($elt) &&
                ($this->_showunsub ||
                 ($this->isSubscribed($elt) && !$this->isContainer($elt)) ||
                 $this->hasChildren($elt)));
    }

    /**
     * Convert a mailbox name to the correct, internal name (i.e. make sure
     * INBOX is always capitalized for IMAP servers).
     *
     * @param string $name  The mailbox name.
     *
     * @return string  The converted name.
     */
    protected function _convertName($name)
    {
        return (strcasecmp($name, 'INBOX') == 0) ? 'INBOX' : $name;
    }

    /**
     * Get namespace info for a full folder path.
     *
     * @param string $mailbox  The folder path.
     *
     * @return mixed  The namespace info for the folder path or null if the
     *                path doesn't exist.
     */
    protected function _getNamespace($mailbox)
    {
        if (!in_array($mailbox, array(self::OTHER_KEY, self::SHARED_KEY, self::VFOLDER_KEY)) &&
            (strpos($mailbox, self::VFOLDER_KEY . $this->_delimiter) !== 0)) {
            return $GLOBALS['imp_imap']->getNamespace($mailbox);
        }
        return null;
    }

    /**
     * Set the start point for determining element differences via eltDiff().
     */
    public function eltDiffStart()
    {
        $this->_eltdiff = array(
            'a' => array(),
            'c' => array(),
            'd' => array()
        );
    }

    /**
     * Return the list of elements that have changed since eltDiffStart()
     * was last called.
     *
     * @return array  Returns false if no changes have occurred, or an array
     *                with the following keys:
     * <pre>
     * 'a' => A list of elements that have been added.
     * 'c' => A list of elements that have been changed.
     * 'd' => A list of elements that have been deleted.
     * </pre>
     */
    public function eltDiff()
    {
        if (is_null($this->_eltdiff) || !$this->_changed) {
            return false;
        }

        $ret = array(
            'a' => array_keys($this->_eltdiff['a']),
            'c' => array_keys($this->_eltdiff['c']),
            'd' => array_keys($this->_eltdiff['d'])
        );

        $this->_eltdiff = null;

        return $ret;
    }

    /**
     * Inserts virtual folders into the tree.
     *
     * @param array $id_list  An array with the folder IDs to add as the key
     *                        and the labels as the value.
     */
    public function insertVFolders($id_list)
    {
        if (empty($id_list) ||
            empty($GLOBALS['conf']['user']['allow_folders'])) {
            return;
        }

        $adds = $id = array();

        foreach ($id_list as $key => $val) {
            $id[$GLOBALS['imp_search']->createSearchID($key)] = $val;
        }

        foreach (array_keys($id) as $key) {
            $id_key = self::VFOLDER_KEY . $this->_delimiter . $key;
            if (!isset($this->_tree[$id_key])) {
                $adds[] = $id_key;
            }
        }

        if (empty($adds)) {
            return;
        }

        $this->insert($adds);

        foreach ($id as $key => $val) {
            $this->_tree[$key]['l'] = $val;
        }

        /* Sort the Virtual Folder list in the object, if necessary. */
        if (!$this->_needSort($this->_tree[self::VFOLDER_KEY])) {
            return;
        }

        $vsort = array();
        foreach ($this->_parent[self::VFOLDER_KEY] as $val) {
            $vsort[$val] = $this->_tree[$val]['l'];
        }
        natcasesort($vsort);
        $this->_parent[self::VFOLDER_KEY] = array_keys($vsort);
        $this->_setNeedSort($this->_tree[self::VFOLDER_KEY], false);
        $this->_changed = true;
    }

    /**
     * Builds a list of folders, suitable to render a folder tree.
     *
     * @param integer $mask  The mask to pass to next().
     * @param boolean $open  If using the base folder icons, display a
     *                       different icon whether the folder is opened or
     *                       closed.
     *
     * @return array  An array with two elements: the folder list and the
     *                total number of new messages.
     *                The folder list array contains the following added
     *                entries on top of the entries provided by element():
     * <pre>
     * 'display' - The mailbox name run through IMP::displayFolder().
     * 'peek' - See peek().
     * </pre>
     */
    public function build($mask = 0, $open = true)
    {
        $newmsgs = $rows = array();
        $this->_forceopen = $open;

        /* Start iterating through the list of mailboxes, displaying them. */
        $mailbox = $this->reset();
        do {
            $row = $this->element($mailbox['v']);

            $row['display'] = $this->_isNonIMAPElt($mailbox)
                ? $mailbox['l']
                : IMP::displayFolder($mailbox['v']);
            $row['peek'] = $this->peek();

            if (!empty($row['recent'])) {
                $newmsgs[$row['value']] = $row['recent'];
            }

            /* Hide folder prefixes from the user. */
            if ($row['level'] >= 0) {
                $rows[] = $row;
            }
        } while (($mailbox = $this->next($mask)));

        $this->_forceopen = false;

        return array($rows, $newmsgs);
    }

    /**
     * Get any custom icon configured for the given element.
     *
     * @params array $elt  A tree element.
     *
     * @return array  An array with the 'icon', 'icondir', and 'alt'
     *                information for the element, or false if no icon
     *                available.
     */
    public function getCustomIcon($elt)
    {
        if (!isset($this->_mboxIcons)) {
            try {
                $this->_mboxIcons = Horde::callHook('mbox_icons', array(), 'imp');
            } catch (Horde_Exception_HookNotSet $e) {
                $this->_mboxIcons = array();
            }
        }

        if (isset($this->_mboxIcons[$elt['v']])) {
            return array(
                'icon' => $this->_mboxIcons[$elt['v']]['icon'],
                'icondir' => $this->_mboxIcons[$elt['v']]['icondir'],
                'alt' => $this->_mboxIcons[$elt['v']]['alt']
            );
        }

        return false;
    }

    /**
     * Returns whether this element is a virtual folder.
     *
     * @param array $elt  A tree element.
     *
     * @return integer  True if the element is a virtual folder.
     */
    public function isVFolder($elt)
    {
        return $elt['a'] & self::ELT_VFOLDER;
    }

    /**
     * Rename a current folder.
     *
     * @param array $old  The old folder names.
     * @param array $new  The new folder names.
     */
    public function rename($old, $new)
    {
        foreach ($old as $key => $val) {
            $polled = isset($this->_tree[$val])
                ? $this->isPolled($this->_tree[$val])
                : false;
            if ($this->delete($val)) {
                $this->insert($new[$key]);
                if ($polled) {
                    $this->addPollList($new[$key]);
                }
            }
        }
    }

    /**
     * Returns a list of all IMAP mailboxes in the tree.
     *
     * @param integer $mask  A mask with the following elements:
     * <pre>
     * IMP_Imap_Tree::FLIST_CONTAINER - Show container elements.
     * IMP_Imap_Tree::FLIST_UNSUB - Show unsubscribed elements.
     * IMP_Imap_Tree::FLIST_VFOLDER - Show Virtual Folders.
     * IMP_Imap_Tree::FLIST_OB - Return full tree object.
     * IMP_Imap_Tree::FLIST_ELT - Return element object.
     * </pre>
     * @param string $base  Return all mailboxes below this element.
     *
     * @return array  Either an array of IMAP mailbox names or an array of
     *                objects (if FLIST_OB ot FLIST_ELT is specified).
     */
    public function folderList($mask = 0, $base = null)
    {
        $baseindex = null;
        $ret_array = array();

        $diff_unsub = (($mask & self::FLIST_UNSUB) != $this->_showunsub)
            ? $this->_showunsub
            : null;
        $this->showUnsubscribed($mask & self::FLIST_UNSUB);

        $mailbox = $this->reset();

        // Search to base element.
        if (!is_null($base)) {
            while ($mailbox && $mailbox['v'] != $base) {
                $mailbox = $this->next(self::NEXT_SHOWCLOSED);
            }
            if ($mailbox) {
                $baseindex = count($this->_currstack);
                $baseparent = $this->_currparent;
                $basekey = $this->_currkey;
                $mailbox = $this->next(self::NEXT_SHOWCLOSED);
            }
        }

        if ($mailbox) {
            do {
                if (!is_null($baseindex) &&
                    (!isset($this->_currstack[$baseindex]) ||
                     ($this->_currstack[$baseindex]['k'] != $basekey) ||
                     ($this->_currstack[$baseindex]['p'] != $baseparent))) {
                    break;
                }

                if ((($mask & self::FLIST_CONTAINER) ||
                     !$this->isContainer($mailbox)) &&
                    (($mask & self::FLIST_VFOLDER) ||
                     !$this->isVFolder($mailbox))) {
                    $ret_array[] = ($mask & self::FLIST_OB)
                        ? $mailbox
                        : (($mask & self::FLIST_ELT) ? $this->element($mailbox['v']) : $mailbox['v']);
                }
            } while (($mailbox = $this->next(self::NEXT_SHOWCLOSED)));
        }

        if (!is_null($diff_unsub)) {
            $this->showUnsubscribed($diff_unsub);
        }

        return $ret_array;
    }

    /**
     * Is the mailbox open in the sidebar?
     *
     * @param array $mbox  A mailbox name.
     *
     * @return integer  True if the mailbox is open in the sidebar.
     */
    public function isOpenSidebar($mbox)
    {
        switch ($GLOBALS['prefs']->getValue('nav_expanded_sidebar')) {
        case self::OPEN_USER:
            $this->_initExpandedList();
            return !empty($this->_expanded[$mbox]);
            break;

        case self::OPEN_ALL:
            return true;
            break;

        case self::OPEN_NONE:
        default:
            return false;
            break;
        }
    }

    /**
     * Init frequently used element() data.
     */
    protected function _initElement()
    {
        if (isset($this->_eltCache)) {
            return;
        }

        /* Initialize the user's identities. */
        $identity = Horde_Prefs_Identity::singleton(array('imp', 'imp'));

        $this->_eltCache = array(
            'trash' => IMP::folderPref($GLOBALS['prefs']->getValue('trash_folder'), true),
            'draft' => IMP::folderPref($GLOBALS['prefs']->getValue('drafts_folder'), true),
            'spam' => IMP::folderPref($GLOBALS['prefs']->getValue('spam_folder'), true),
            'sent' => $identity->getAllSentmailFolders(),
            'image_dir' => $GLOBALS['registry']->getImageDir(),
        );
    }

    /**
     * Return extended information on an element.
     *
     * @param mixed $name  The name of the tree element or a tree element.
     *
     * @return array  Returns the element with extended information, or false
     *                if not found.  The information returned is as follows:
     * <pre>
     * 'alt' - (string) The alt text for the icon.
     * 'base_elt' - (array) The return from get().
     * 'children' - (boolean) Does the element have children?
     * 'class' - (string) The CSS class name.
     * 'container' - (boolean) Is this a container element?
     * 'editvfolder' - (boolean) Can this virtual folder be edited?
     * 'icon' - (string) The name of the icon graphic to use.
     * 'icondir' - (string) The path of the icon directory.
     * 'iconopen' - ???
     * 'level' - (integer) The deepness level of this element.
     * 'mbox_val' - (string) A html-ized version of 'value'.
     * 'msgs' - (integer) The number of total messages in the element (if
     *          polled).
     * 'name' - (string) A html-ized version of 'label'.
     * 'nonimap' - (boolean) Is this a non-IMAP element?
     * 'parent' - (array) The parent element value.
     * 'polled' - (boolean) Show polled information?
     * 'recent' - (integer) The number of new messages in the element (if
     *            polled).
     * 'sub' - (boolean) Is folder subscribed to?
     * 'special' - (boolean) Is this is a "special" element?
     * 'specialvfolder' - (boolean) Is this a "special" virtual folder?
     * 'unseen' - (integer) The number of unseen messages in the element (if
     *            polled).
     * 'user_icon' - (boolean) Use a user defined icon?
     * 'value' - (string) The value of this element (i.e. element id).
     * 'vfolder' - (boolean) Is this a virtual folder?
     * </pre>
     */
    public function element($mailbox)
    {
        if (!is_array($mailbox)) {
            $mailbox = $this->get($mailbox);
        }

        if (!$mailbox) {
            return false;
        }

        $this->_initElement();

        $row = array(
            'base_elt' => $mailbox,
            'children' => $this->hasChildren($mailbox),
            'container' => false,
            'editvfolder' => false,
            'icondir' => $this->_eltCache['image_dir'],
            'iconopen' => null,
            'level' => $mailbox['c'],
            'mbox_val' => htmlspecialchars($mailbox['v']),
            'name' => htmlspecialchars($mailbox['l']),
            'nonimap' => $this->_isNonIMAPElt($mailbox),
            'parent' => $mailbox['p'],
            'polled' => false,
            'recent' => 0,
            'special' => false,
            'specialvfolder' => false,
            'sub' => $this->isSubscribed($mailbox),
            'user_icon' => false,
            'value' => $mailbox['v'],
            'vfolder' => false,
        );

        $icon = $this->getCustomIcon($mailbox);

        if (!$this->isContainer($mailbox)) {
            /* We are dealing with mailboxes here.
             * Determine if we need to poll this mailbox for new messages. */
            if ($this->isPolled($mailbox)) {
                /* If we need message information for this folder, update
                 * it now. */
                $msgs_info = $this->getElementInfo($mailbox['v']);
                if (!empty($msgs_info)) {
                    $row['polled'] = true;
                    if (!empty($msgs_info['recent'])) {
                        $row['recent'] = $msgs_info['recent'];
                    }
                    $row['msgs'] = $msgs_info['messages'];
                    $row['unseen'] = $msgs_info['unseen'];
                }
            }

            switch ($mailbox['v']) {
            case 'INBOX':
                $row['icon'] = 'folders/inbox.png';
                $row['alt'] = _("Inbox");
                $row['class'] = 'inboxImg';
                $row['special'] = true;
                break;

            case $this->_eltCache['trash']:
                if ($GLOBALS['prefs']->getValue('use_vtrash')) {
                    $row['icon'] = ($this->isOpen($mailbox)) ? 'folders/open.png' : 'folders/folder.png';
                    $row['alt'] = _("Mailbox");
                } else {
                    $row['icon'] = 'folders/trash.png';
                    $row['alt'] = _("Trash folder");
                    $row['class'] = 'trashImg';
                    $row['special'] = true;
                }
                break;

            case $this->_eltCache['draft']:
                $row['icon'] = 'folders/drafts.png';
                $row['alt'] = _("Draft folder");
                $row['class'] = 'draftsImg';
                $row['special'] = true;
                break;

            case $this->_eltCache['spam']:
                $row['icon'] = 'folders/spam.png';
                $row['alt'] = _("Spam folder");
                $row['class'] = 'spamImg';
                $row['special'] = true;
                break;

            default:
                if (in_array($mailbox['v'], $this->_eltCache['sent'])) {
                    $row['icon'] = 'folders/sent.png';
                    $row['alt'] = _("Sent mail folder");
                    $row['class'] = 'sentImg';
                    $row['special'] = true;
                } else {
                    if ($this->isOpen($mailbox)) {
                        $row['icon'] = 'folders/open.png';
                        $row['class'] = 'folderopenImg';
                    } else {
                        $row['icon'] = 'folders/folder.png';
                        $row['class'] = 'folderImg';
                    }
                    $row['alt'] = _("Mailbox");
                }
                break;
            }

            /* Virtual folders. */
            if ($this->isVFolder($mailbox)) {
                $row['vfolder'] = true;
                $row['editvfolder'] = $GLOBALS['imp_search']->isEditableVFolder($mailbox['v']);
                if ($GLOBALS['imp_search']->isVTrashFolder($mailbox['v'])) {
                    $row['specialvfolder'] = true;
                    $row['icon'] = 'folders/trash.png';
                    $row['alt'] = _("Virtual Trash Folder");
                    $row['class'] = 'trashImg';
                } elseif ($GLOBALS['imp_search']->isVINBOXFolder($mailbox['v'])) {
                    $row['specialvfolder'] = true;
                    $row['icon'] = 'folders/inbox.png';
                    $row['alt'] = _("Virtual INBOX Folder");
                    $row['class'] = 'inboxImg';
                }
            }
        } else {
            /* We are dealing with folders here. */
            $row['container'] = true;
            if ($this->_forceopen && $this->isOpen($mailbox)) {
                $row['icon'] = 'folders/open.png';
                $row['alt'] = _("Opened Folder");
                $row['class'] = 'folderopenImg';
            } else {
                $row['icon'] = 'folders/folder.png';
                $row['iconopen'] = 'folders/open.png';
                $row['alt'] = ($this->_forceopen) ? _("Closed Folder") : _("Folder");
                $row['class'] = 'folderImg';
            }
            if ($this->isVFolder($mailbox)) {
                $row['vfolder'] = true;
            }
        }

        /* Overwrite the icon information now. */
        if (!empty($icon)) {
            $row['icon'] = $icon['icon'];
            $row['icondir'] = $icon['icondir'];
            if (!empty($icon['alt'])) {
                $row['alt'] = $icon['alt'];
            }
            $row['iconopen'] = isset($icon['iconopen'])
                ? $icon['iconopen']
                : null;
            $row['user_icon'] = true;
        }

        return $row;
    }

    /**
     * Sort a level in the tree.
     *
     * @param string $id  The parent folder whose children need to be sorted.
     */
    protected function _sortLevel($id)
    {
        if ($this->_needSort($this->_tree[$id])) {
            $this->_sortList($this->_parent[$id], ($id === self::BASE_ELT));
            $this->_setNeedSort($this->_tree[$id], false);
            $this->_changed = true;
        }
    }

    /**
     * Determines the mailbox name to create given a parent and the new name.
     *
     * @param string $parent  The parent name (UTF7-IMAP).
     * @param string $parent  The new mailbox name (UTF7-IMAP).
     *
     * @return string  The full path to the new mailbox.
     * @throws Horde_Exception
     */
    public function createMailboxName($parent, $new)
    {
        $ns_info = empty($parent)
            ? $GLOBALS['imp_imap']->defaultNamespace()
            : $this->_getNamespace($parent);

        if (is_null($ns_info)) {
            if ($this->isNamespace($this->_tree[$parent])) {
                $ns_info = $this->_getNamespace($new);
                if (in_array($ns_info['type'], array('other', 'shared'))) {
                    return $new;
                }
            }
            throw new Horde_Exception(_("Cannot directly create mailbox in this folder."), 'horde.error');
        }

        $mbox = $ns_info['name'];
        if (!empty($parent)) {
            $mbox .= substr_replace($parent, '', 0, strlen($ns_info['name']));
            $mbox = rtrim($mbox, $ns_info['delimiter']) . $ns_info['delimiter'];
        }
        return $mbox . $new;
    }

}
