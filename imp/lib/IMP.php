<?php
/**
 * IMP Base Class.
 *
 * Copyright 1999-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jon Parise <jon@horde.org>
 * @author  Michael Slusarz <slusarz@horde.org>
 * @package IMP
 */
class IMP
{
    /* Encrypt constants. */
    const ENCRYPT_NONE = 1;
    const PGP_ENCRYPT = 2;
    const PGP_SIGN = 3;
    const PGP_SIGNENC = 4;
    const SMIME_ENCRYPT = 5;
    const SMIME_SIGN = 6;
    const SMIME_SIGNENC = 7;
    const PGP_SYM_ENCRYPT = 8;
    const PGP_SYM_SIGNENC = 9;

    /* IMP Mailbox view constants. */
    const MAILBOX_START_FIRSTUNSEEN = 1;
    const MAILBOX_START_LASTUNSEEN = 2;
    const MAILBOX_START_FIRSTPAGE = 3;
    const MAILBOX_START_LASTPAGE = 4;

    /* IMP internal string used to separate indexes. */
    const IDX_SEP = '\0';

    /* Preferences constants. */
    const PREF_NO_FOLDER = 'nofolder\0';
    const PREF_VTRASH = 'vtrash\0';

    /* Storage place for an altered version of the current URL. */
    static public $newUrl = null;

    /* displayFolder() cache. */
    static private $_displaycache = array();

    /* hideDeletedMsgs() cache. */
    static private $_delhide = null;

    /* prepareMenu() cache. */
    static private $_menuTemplate = null;

    /* Has init previously been called? */
    static private $_init = false;

    /**
     * Performs IMP initialization.
     */
    static public function initialize()
    {
        if (self::$_init) {
            return;
        }

        if (!defined('IMP_TEMPLATES')) {
            $registry = Horde_Registry::singleton();
            define('IMP_TEMPLATES', $registry->get('templates'));
        }

        // Start compression.
        if (!IMP_Application::$noCompress) {
            Horde::compressOutput();
        }

        // Initialize global $imp_imap object.
        if (!isset($GLOBALS['imp_imap'])) {
            $GLOBALS['imp_imap'] = new IMP_Imap();
        }

        // Initialize some message parsing variables.
        Horde_Mime::$brokenRFC2231 = !empty($GLOBALS['conf']['mailformat']['brokenrfc2231']);

        // Set default message character set, if necessary
        if ($def_charset = $GLOBALS['prefs']->getValue('default_msg_charset')) {
            Horde_Mime_Part::$defaultCharset = $def_charset;
            Horde_Mime_Headers::$defaultCharset = $def_charset;
        }

        $GLOBALS['notification'] = Horde_Notification::singleton();
        $viewmode = self::getViewMode();

        if ($viewmode == 'mimp') {
            $GLOBALS['imp_notify'] = $GLOBALS['notification']->attach('status', null, 'IMP_Notification_Listener_StatusMobile');
        } else {
            $GLOBALS['imp_notify'] = $GLOBALS['notification']->attach('status', array('viewmode' => $viewmode), 'IMP_Notification_Listener_Status');
            if ($viewmode == 'imp') {
                $GLOBALS['notification']->attach('audio');
            }
        }

        // Initialize global $imp_mbox array. This call also initializes the
        // IMP_Search object.
        IMP::setCurrentMailboxInfo();

        self::$_init = true;
    }

    /**
     * Returns the current view mode for IMP.
     *
     * @return string  Either 'dimp', 'imp', or 'mimp'.
     */
    static public function getViewMode()
    {
        return isset($_SESSION['imp']['view'])
            ? $_SESSION['imp']['view']
            : 'imp';
    }

    /**
     * Returns the plain text label that is displayed for the current mailbox,
     * replacing virtual search mailboxes with an appropriate description and
     * removing namespace and mailbox prefix information from what is shown to
     * the user.
     *
     * @param string $mbox  The mailbox to use for the label.
     *
     * @return string  The plain text label.
     */
    static public function getLabel($mbox)
    {
        return IMP_Search::isSearchMbox($mbox)
            ? $GLOBALS['imp_search']->getLabel($mbox)
            : self::displayFolder($mbox);
    }

    /**
     * Adds a contact to the user defined address book.
     *
     * @param string $newAddress  The contact's email address.
     * @param string $newName     The contact's name.
     *
     * @return string  A link or message to show in the notification area.
     * @throws Horde_Exception
     */
    static public function addAddress($newAddress, $newName)
    {
        global $registry, $prefs;

        if (empty($newName)) {
            $newName = $newAddress;
        }

        $result = $registry->call('contacts/import', array(array('name' => $newName, 'email' => $newAddress), 'array', $prefs->getValue('add_source')));

        $contact_link = $registry->link('contacts/show', array('uid' => $result, 'source' => $prefs->getValue('add_source')));

        $old_error = error_reporting(0);
        $escapeName = htmlspecialchars($newName, ENT_COMPAT, Horde_Nls::getCharset());
        error_reporting($old_error);

        return (!empty($contact_link) && !($contact_link instanceof PEAR_Error))
            ? Horde::link(Horde::url($contact_link), sprintf(_("Go to address book entry of \"%s\""), $newName)) . $escapeName . '</a>'
            : $escapeName;
    }

    /**
     * Wrapper around IMP_Folder::flist() which generates the body of a
     * &lt;select&gt; form input from the generated folder list. The
     * &lt;select&gt; and &lt;/select&gt; tags are NOT included in the output
     * of this function.
     *
     * @param array $options  Optional parameters:
     * <pre>
     * 'abbrev' - (boolean) Abbreviate long mailbox names by replacing the
     *            middle of the name with '...'?
     *            DEFAULT: Yes
     * 'filter' - (array) An array of mailboxes to ignore.
     *            DEFAULT: Display all
     * 'heading' - (string) The label for an empty-value option at the top of
     *             the list.
     *             DEFAULT: ''
     * 'inc_notepads' - (boolean) Include user's editable notepads in list?
     *                   DEFAULT: No
     * 'inc_tasklists' - (boolean) Include user's editable tasklists in list?
     *                   DEFAULT: No
     * 'inc_vfolder' - (boolean) Include user's virtual folders in list?
     *                   DEFAULT: No
     * 'new_folder' - (boolean) Display an option to create a new folder?
     *                DEFAULT: No
     * 'selected' - (string) The mailbox to have selected by default.
     *             DEFAULT: None
     * </pre>
     *
     * @return string  A string containing <option> elements for each mailbox
     *                 in the list.
     */
    static public function flistSelect($options = array())
    {
        $imp_folder = IMP_Folder::singleton();

        /* Don't filter here - since we are going to parse through every
         * member of the folder list below anyway, we can filter at that time.
         * This allows us the have a single cached value for the folder list
         * rather than a cached value for each different mailbox we may
         * visit. */
        $mailboxes = $imp_folder->flist();
        $text = '';

        if (!empty($options['heading']) &&
            (strlen($options['heading']) > 0)) {
            $text .= '<option value="">' . $options['heading'] . "</option>\n";
        }

        if (!empty($options['new_folder']) &&
            (!empty($GLOBALS['conf']['hooks']['permsdenied']) ||
             ($GLOBALS['perms']->hasAppPermission('create_folders') &&
              $GLOBALS['perms']->hasAppPermission('max_folders')))) {
            $text .= "<option value=\"\" disabled=\"disabled\">- - - - - - - -</option>\n" .
                '<option value="*new*">' . _("New Folder") . "</option>\n" .
                "<option value=\"\" disabled=\"disabled\">- - - - - - - -</option>\n";
        }

        /* Add the list of mailboxes to the lists. */
        $filter = empty($options['filter']) ? array() : array_flip($options['filter']);
        foreach ($mailboxes as $mbox) {
            if (isset($filter[$mbox['val']])) {
                continue;
            }

            $val = isset($filter[$mbox['val']]) ? '' : htmlspecialchars($mbox['val']);
            $sel = ($mbox['val'] && !empty($options['selected']) && ($mbox['val'] === $options['selected'])) ? ' selected="selected"' : '';
            $label = empty($options['abbrev']) ? $mbox['label'] : $mbox['abbrev'];
            $text .= sprintf('<option value="%s"%s>%s</option>%s', $val, $sel, Horde_Text_Filter::filter($label, 'space2html', array('charset' => Horde_Nls::getCharset(), 'encode' => true)), "\n");
        }

        /* Add the list of virtual folders to the list. */
        if (!empty($options['inc_vfolder'])) {
            $vfolders = $GLOBALS['imp_search']->listQueries(IMP_Search::LIST_VFOLDER);
            if (!empty($vfolders)) {
                $vfolder_sel = $GLOBALS['imp_search']->searchMboxID();
                $text .= '<option value="" disabled="disabled">- - - - - - - - -</option>' . "\n";
                foreach ($vfolders as $id => $val) {
                    $text .= sprintf('<option value="%s"%s>%s</option>%s', $GLOBALS['imp_search']->createSearchID($id), ($vfolder_sel == $id) ? ' selected="selected"' : '', Horde_Text_Filter::filter($val, 'space2html', array('charset' => Horde_Nls::getCharset(), 'encode' => true)), "\n");
                }
            }
        }

        /* Add the list of editable tasklists to the list. */
        if (!empty($options['inc_tasklists']) &&
            !empty($_SESSION['imp']['tasklistavail'])) {
            try {
                $tasklists = $GLOBALS['registry']->call('tasks/listTasklists', array(false, Horde_Perms::EDIT));

                if (count($tasklists)) {
                    $text .= '<option value="" disabled="disabled">&nbsp;</option><option value="" disabled="disabled">- - ' . _("Task Lists") . ' - -</option>' . "\n";

                    foreach ($tasklists as $id => $tasklist) {
                        $text .= sprintf('<option value="%s">%s</option>%s',
                                         '_tasklist_' . $id,
                                         Horde_Text_Filter::filter($tasklist->get('name'), 'space2html', array('charset' => Horde_Nls::getCharset(), 'encode' => true)),
                                         "\n");
                    }
                }
            } catch (Horde_Exception $e) {}
        }

        /* Add the list of editable notepads to the list. */
        if (!empty($options['inc_notepads']) &&
            !empty($_SESSION['imp']['notepadavail'])) {
            try {
                $notepads = $GLOBALS['registry']->call('notes/listNotepads', array(false, Horde_Perms::EDIT));
                if (count($notepads)) {
                    $text .= '<option value="" disabled="disabled">&nbsp;</option><option value="" disabled="disabled">- - ' . _("Notepads") . " - -</option>\n";

                    foreach ($notepads as $id => $notepad) {
                        $text .= sprintf('<option value="%s">%s</option>%s',
                                         '_notepad_' . $id,
                                         Horde_Text_Filter::filter($notepad->get('name'), 'space2html', array('charset' => Horde_Nls::getCharset(), 'encode' => true)),
                                         "\n");
                    }
                }
            } catch (Horde_Exception $e) {}
        }

        return $text;
    }

    /**
     * Checks for To:, Subject:, Cc:, and other compose window arguments and
     * pass back either a URI fragment or an associative array with any of
     * them which are present.
     *
     * @param string $format  Either 'uri' or 'array'.
     *
     * @return string  A URI fragment or an associative array with any compose
     *                 arguments present.
     */
    static public function getComposeArgs()
    {
        $args = array();
        $fields = array('to', 'cc', 'bcc', 'message', 'body', 'subject');

        foreach ($fields as $val) {
            if (($$val = Horde_Util::getFormData($val))) {
                $args[$val] = $$val;
            }
        }

        /* Decode mailto: URLs. */
        if (isset($args['to']) && (strpos($args['to'], 'mailto:') === 0)) {
            $mailto = @parse_url($args['to']);
            if (is_array($mailto)) {
                $args['to'] = isset($mailto['path']) ? $mailto['path'] : '';
                if (!empty($mailto['query'])) {
                    parse_str($mailto['query'], $vals);
                    foreach ($fields as $val) {
                        if (isset($vals[$val])) {
                            $args[$val] = $vals[$val];
                        }
                    }
                }
            }
        }

        return $args;
    }

    /**
     * Open an (IMP) compose window.
     *
     * @return boolean  True if window was opened.
     */
    static public function openComposeWin($options = array())
    {
        if ($GLOBALS['prefs']->getValue('compose_popup')) {
            return false;
        }

        $options += self::getComposeArgs();
        $url = Horde_Util::addParameter(Horde::applicationUrl('compose.php', true), $options, null, false);
        header('Location: ' . $url);
        return true;
    }

    /**
     * Prepares the arguments to use for composeLink().
     *
     * @param mixed $args   List of arguments to pass to compose.php. If this
     *                      is passed in as a string, it will be parsed as a
     *                      toaddress?subject=foo&cc=ccaddress (mailto-style)
     *                      string.
     * @param array $extra  Hash of extra, non-standard arguments to pass to
     *                      compose.php.
     *
     * @return array  The array of args to use for composeLink().
     */
    static public function composeLinkArgs($args = array(), $extra = array())
    {
        if (is_string($args)) {
            $string = $args;
            $args = array();
            if (($pos = strpos($string, '?')) !== false) {
                parse_str(substr($string, $pos + 1), $args);
                $args['to'] = substr($string, 0, $pos);
            } else {
                $args['to'] = $string;
            }
        }

        /* Merge the two argument arrays. */
        return (is_array($extra) && !empty($extra))
            ? array_merge($args, $extra)
            : $args;
    }

    /**
     * Returns the appropriate link to call the message composition screen.
     *
     * @param mixed $args   List of arguments to pass to compose.php. If this
     *                      is passed in as a string, it will be parsed as a
     *                      toaddress?subject=foo&cc=ccaddress (mailto-style)
     *                      string.
     * @param array $extra  Hash of extra, non-standard arguments to pass to
     *                      compose.php.
     * @param string $view  The IMP view to create a link for.
     *
     * @return string  The link to the message composition screen.
     */
    static public function composeLink($args = array(), $extra = array(),
                                       $view = null)
    {
        $args = self::composeLinkArgs($args, $extra);

        if (is_null($view)) {
            $view = self::getViewMode();
        }

        if ($view == 'dimp') {
            // IE 6 & 7 handles window.open() URL param strings differently if
            // triggered via an href or an onclick.  Since we have no hint
            // at this point where this link will be used, we have to always
            // encode the params and explicitly call rawurlencode() in
            // compose.php.
            $encode_args = array('popup' => 1);
            foreach ($args as $k => $v) {
                $encode_args[$k] = rawurlencode($v);
            }
            return 'javascript:void(window.open(\'' . Horde_Util::addParameter(Horde::applicationUrl('compose-dimp.php'), $encode_args, null, false) . '\', \'\', \'width=820,height=610,status=1,scrollbars=yes,resizable=yes\'));';
        }

        if (($view != 'mimp') &&
            $GLOBALS['prefs']->getValue('compose_popup') &&
            $GLOBALS['browser']->hasFeature('javascript')) {
            if (isset($args['to'])) {
                $args['to'] = addcslashes($args['to'], '\\"');
            }
            return "javascript:" . Horde::popupJs(Horde::applicationUrl('compose.php'), array('params' => $args, 'urlencode' => true));
        }

        return Horde_Util::addParameter(Horde::applicationUrl(($view == 'mimp') ? 'compose-mimp.php' : 'compose.php'), $args);
    }

    /**
     * If there is information available to tell us about a prefix in front of
     * mailbox names that shouldn't be displayed to the user, then use it to
     * strip that prefix out. Additionally, translate prefix text if this
     * is one of the folders with special meaning.
     *
     * @param string $folder        The folder name to display (UTF7-IMAP).
     * @param boolean $notranslate  Do not translate the folder prefix.
     *
     * @return string  The folder, with any prefix gone/translated.
     */
    static public function displayFolder($folder, $notranslate = false)
    {
        global $prefs;

        $cache = &self::$_displaycache;

        if (!$notranslate && isset($cache[$folder])) {
            return $cache[$folder];
        }

        $ns_info = $GLOBALS['imp_imap']->getNamespace($folder);
        $delimiter = is_null($ns_info) ? '' : $ns_info['delimiter'];

        /* Substitute any translated prefix text. */
        $sub_array = array(
            'INBOX' => _("Inbox"),
            $prefs->getValue('sent_mail_folder') => _("Sent"),
            $prefs->getValue('drafts_folder') => _("Drafts"),
            $prefs->getValue('trash_folder') => _("Trash"),
            $prefs->getValue('spam_folder') => _("Spam")
        );

        /* Strip namespace information. */
        if (!is_null($ns_info) &&
            !empty($ns_info['name']) &&
            ($ns_info['type'] == 'personal') &&
            substr($folder, 0, strlen($ns_info['name'])) == $ns_info['name']) {
            $out = substr($folder, strlen($ns_info['name']));
        } else {
            $out = $folder;
        };

        if ($notranslate) {
            return $out;
        }

        foreach ($sub_array as $key => $val) {
            if (stripos($out, $key) === 0) {
                $len = strlen($key);
                if ((strlen($out) == $len) || ($out[$len] == $delimiter)) {
                    $out = substr_replace($out, Horde_String::convertCharset($val, Horde_Nls::getCharset(), 'UTF7-IMAP'), 0, $len);
                    break;
                }
            }
        }

        $cache[$folder] = Horde_String::convertCharset($out, 'UTF7-IMAP');

        return $cache[$folder];
    }

    /**
     * Filters a string, if requested.
     *
     * @param string $text  The text to filter.
     *
     * @return string  The filtered text (if requested).
     */
    static public function filterText($text)
    {
        if ($GLOBALS['prefs']->getValue('filtering') && strlen($text)) {
            return Horde_Text_Filter::filter($text, 'words', array('words_file' => $GLOBALS['conf']['msgsettings']['filtering']['words'], 'replacement' => $GLOBALS['conf']['msgsettings']['filtering']['replacement']));
        }

        return $text;
    }

    /**
     * Build IMP's list of menu items.
     *
     * @return Horde_Menu  A Horde_Menu object.
     */
    static public function getMenu()
    {
        global $conf, $prefs, $registry;

        $menu_search_url = Horde::applicationUrl('search.php');
        $menu_mailbox_url = Horde::applicationUrl('mailbox.php');

        $spam_folder = self::folderPref($prefs->getValue('spam_folder'), true);

        $menu = new Horde_Menu();

        $menu->add(self::generateIMPUrl($menu_mailbox_url, 'INBOX'), _("_Inbox"), 'folders/inbox.png');

        if ($_SESSION['imp']['protocol'] != 'pop') {
            if ($prefs->getValue('use_trash') &&
                $prefs->getValue('empty_trash_menu')) {
                $mailbox = null;
                if ($prefs->getValue('use_vtrash')) {
                    $mailbox = $GLOBALS['imp_search']->createSearchID($prefs->getValue('vtrash_id'));
                } else {
                    $trash_folder = self::folderPref($prefs->getValue('trash_folder'), true);
                    if (!is_null($trash_folder)) {
                        $mailbox = $trash_folder;
                    }
                }

                if (!empty($mailbox) && !$GLOBALS['imp_imap']->isReadOnly($mailbox)) {
                    $menu_trash_url = Horde_Util::addParameter(self::generateIMPUrl($menu_mailbox_url, $mailbox), array('actionID' => 'empty_mailbox', 'mailbox_token' => Horde::getRequestToken('imp.mailbox')));
                    $menu->add($menu_trash_url, _("Empty _Trash"), 'empty_trash.png', null, null, "return window.confirm('" . addslashes(_("Are you sure you wish to empty your trash folder?")) . "');", '__noselection');
                }
            }

            if (!empty($spam_folder) &&
                $prefs->getValue('empty_spam_menu')) {
                $menu_spam_url = Horde_Util::addParameter(self::generateIMPUrl($menu_mailbox_url, $spam_folder), array('actionID' => 'empty_mailbox', 'mailbox_token' => Horde::getRequestToken('imp.mailbox')));
                $menu->add($menu_spam_url, _("Empty _Spam"), 'empty_spam.png', null, null, "return window.confirm('" . addslashes(_("Are you sure you wish to empty your spam folder?")) . "');", '__noselection');
            }
        }

        if (self::canCompose()) {
            $menu->add(self::composeLink(array('mailbox' => $GLOBALS['imp_mbox']['mailbox'])), _("_New Message"), 'compose.png');
        }

        if ($conf['user']['allow_folders']) {
            $menu->add(Horde_Util::nocacheUrl(Horde::applicationUrl('folders.php')), _("_Folders"), 'folders/folder.png');
        }

        if ($_SESSION['imp']['protocol'] != 'pop') {
            $menu->add($menu_search_url, _("_Search"), 'search.png');
        }

        if ($prefs->getValue('filter_menuitem')) {
            $menu->add(Horde::applicationUrl('filterprefs.php'), _("Fi_lters"), 'filters.png');
        }

        return $menu;
    }

    /**
     * Build IMP's list of menu items.
     */
    static public function prepareMenu()
    {
        if (isset(self::$_menuTemplate)) {
            return;
        }

        $t = new Horde_Template();
        $t->set('forminput', Horde_Util::formInput());
        $t->set('use_folders', ($_SESSION['imp']['protocol'] != 'pop') && $GLOBALS['conf']['user']['allow_folders'], true);
        if ($t->get('use_folders')) {
            Horde::addScriptFile('imp.js', 'imp');
            $menu_view = $GLOBALS['prefs']->getValue('menu_view');
            $ak = $GLOBALS['prefs']->getValue('widget_accesskey')
                ? Horde::getAccessKey(_("Open Fo_lder"))
                : '';

            $t->set('ak', $ak);
            $t->set('flist', self::flistSelect(array('selected' => $GLOBALS['imp_mbox']['mailbox'], 'inc_vfolder' => true)));
            $t->set('flink', sprintf('%s%s<br />%s</a>', Horde::link('#'), ($menu_view != 'text') ? Horde::img('folders/open.png', _("Open Folder"), ($menu_view == 'icon') ? array('title' => _("Open Folder")) : array()) : '', ($menu_view != 'icon') ? Horde::highlightAccessKey(_("Open Fo_lder"), $ak) : ''));
        }
        $t->set('menu_string', self::getMenu()->render());

        self::$_menuTemplate = $t;
    }

    /**
     * Outputs IMP's menu to the current output stream.
     */
    static public function menu()
    {
        self::prepareMenu();
        echo self::$_menuTemplate->fetch(IMP_TEMPLATES . '/menu.html');
    }

    /**
     * Outputs IMP's status/notification bar.
     */
    static public function status()
    {
        $notification = Horde_Notification::singleton();
        $notification->notify(array('listeners' => array('status', 'audio')));
    }

    /**
     * Outputs IMP's quota information.
     */
    static public function quota()
    {
        $quotadata = self::quotaData(true);
        if (!empty($quotadata)) {
            $t = new Horde_Template();
            $t->set('class', $quotadata['class']);
            $t->set('message', $quotadata['message']);
            echo $t->fetch(IMP_TEMPLATES . '/quota/quota.html');
        }
    }

    /**
     * Returns data needed to output quota.
     *
     * @param boolean $long  Output long messages?
     *
     * @return array  Array with these keys: class, message, percent.
     */
    static public function quotaData($long = true)
    {
        if (!isset($_SESSION['imp']['quota']) ||
            !is_array($_SESSION['imp']['quota'])) {
            return false;
        }

        try {
            $quotaDriver = IMP_Quota::singleton($_SESSION['imp']['quota']['driver'], $_SESSION['imp']['quota']['params']);
            $quota = $quotaDriver->getQuota();
        } catch (Horde_Exception $e) {
            Horde::logMessage($e, __FILE__, __LINE__, PEAR_LOG_ERR);
            return false;
        }

        $strings = $quotaDriver->getMessages();
        list($calc, $unit) = $quotaDriver->getUnit();
        $ret = array('percent' => 0);

        if ($quota['limit'] != 0) {
            $quota['usage'] = $quota['usage'] / $calc;
            $quota['limit'] = $quota['limit'] / $calc;
            $ret['percent'] = ($quota['usage'] * 100) / $quota['limit'];
            if ($ret['percent'] >= 90) {
                $ret['class'] = 'quotaalert';
            } elseif ($ret['percent'] >= 75) {
                $ret['class'] = 'quotawarn';
            } else {
                $ret['class'] = 'control';
            }

            $ret['message'] = $long
                ? sprintf($strings['long'], $quota['usage'], $unit, $quota['limit'], $unit, $ret['percent'])
                : sprintf($strings['short'], $ret['percent'], $quota['limit'], $unit);
            $ret['percent'] = sprintf("%.2f", $ret['percent']);
        } else {
            // Hide unlimited quota message?
            if (!empty($_SESSION['imp']['quota']['hide_when_unlimited'])) {
                return false;
            }

            $ret['class'] = 'control';
            if ($quota['usage'] != 0) {
                $quota['usage'] = $quota['usage'] / $calc;

                $ret['message'] = $long
                    ? sprintf($strings['nolimit_long'], $quota['usage'], $unit)
                    : sprintf($strings['nolimit_short'], $quota['usage'], $unit);
            } else {
                $ret['message'] = $long
                    ? sprintf(_("Quota status: NO LIMIT"))
                    : _("No limit");
            }
        }

        return $ret;
    }

    /**
     * Outputs the necessary javascript code to display the new mail
     * notification message.
     *
     * @param mixed $var  Either an associative array with mailbox names as
     *                    the keys and the message count as the values or
     *                    an integer indicating the number of new messages
     *                    in the current mailbox.
     *
     * @return string  The javascript for the popup message.
     */
    static public function getNewMessagePopup($var)
    {
        $t = new Horde_Template();
        $t->setOption('gettext', true);
        if (is_array($var)) {
            if (empty($var)) {
                return;
            }
            $folders = array();
            foreach ($var as $mb => $nm) {
                $folders[] = array(
                    'url' => Horde_Util::addParameter(self::generateIMPUrl('mailbox.php', $mb), 'no_newmail_popup', 1),
                    'name' => htmlspecialchars(self::displayFolder($mb)),
                    'new' => (int)$nm,
                );
            }
            $t->set('folders', $folders);

            if (($_SESSION['imp']['protocol'] != 'pop') &&
                $GLOBALS['prefs']->getValue('use_vinbox') &&
                ($vinbox_id = $GLOBALS['prefs']->getValue('vinbox_id'))) {
                $t->set('vinbox', Horde::link(Horde_Util::addParameter(self::generateIMPUrl('mailbox.php', $GLOBALS['imp_search']->createSearchID($vinbox_id)), 'no_newmail_popup', 1)));
            }
        } else {
            $t->set('msg', ($var == 1) ? _("You have 1 new message.") : sprintf(_("You have %s new messages."), $var));
        }
        $t_html = str_replace("\n", ' ', $t->fetch(IMP_TEMPLATES . '/newmsg/alert.html'));

        Horde::addScriptFile('effects.js', 'horde');
        Horde::addScriptFile('redbox.js', 'horde');
        return 'RedBox.overlay = false; RedBox.showHtml(\'' . addcslashes($t_html, "'/") . '\');';
    }

    /**
     * Get message indices list.
     *
     * @param mixed $indices  The following inputs are allowed:
     * <pre>
     * 1. An array of messages indices in the following format:
     *    msg_id IMP::IDX_SEP msg_mbox
     *      msg_id      = Message index of the message
     *      IMP::IDX_SEP = IMP constant used to separate index/mailbox
     *      msg_folder  = The full mailbox name containing the message index
     * 2. An array with the full folder name as keys and an array of message
     *    indices as the values.
     * </pre>
     *
     * @return mixed  Returns an array with the folder as key and an array
     *                of message indices as the value (See #2 above).
     *                Else, returns false.
     */
    static public function parseIndicesList($indices)
    {
        if (!is_array($indices) || empty($indices)) {
            return array();
        }

        $msgList = array();

        reset($indices);
        if (!is_array(current($indices))) {
            /* Build the list of indices/mailboxes if input is format #1. */
            while (list(,$msgIndex) = each($indices)) {
                if (strpos($msgIndex, self::IDX_SEP) === false) {
                    return false;
                } else {
                    list($val, $key) = explode(self::IDX_SEP, $msgIndex);
                    $msgList[$key][] = $val;
                }
            }
        } else {
            /* We are dealing with format #2. */
            while (list($key, $val) = each($indices)) {
                if ($GLOBALS['imp_search']->isSearchMbox($key)) {
                    $msgList += self::parseIndicesList($val);
                } else {
                    /* Make sure we don't have any duplicate keys. */
                    $msgList[$key] = is_array($val)
                        ? array_keys(array_flip($val))
                        : array($val);
                }
            }
        }

        return $msgList;
    }

    /**
     * Convert a preference value to/from the value stored in the preferences.
     *
     * To allow folders from the personal namespace to be stored without this
     * prefix for portability, we strip the personal namespace. To tell apart
     * folders from the personal and any empty namespace, we prefix folders
     * from the empty namespace with the delimiter.
     *
     * @param string $folder   The folder path.
     * @param boolean $append  True - convert from preference value.
     *                         False - convert to preference value.
     *
     * @return string  The folder name.
     */
    static public function folderPref($folder, $append)
    {
        $def_ns = $GLOBALS['imp_imap']->defaultNamespace();
        $empty_ns = $GLOBALS['imp_imap']->getNamespace('');

        if ($append) {
            /* Converting from preference value. */
            if (!is_null($empty_ns) &&
                strpos($folder, $empty_ns['delimiter']) === 0) {
                /* Prefixed with delimiter => from empty namespace. */
                $folder = substr($folder, strlen($empty_ns['delimiter']));
            } elseif (($ns = $GLOBALS['imp_imap']->getNamespace($folder)) == null) {
                /* No namespace prefix => from personal namespace. */
                $folder = $def_ns['name'] . $folder;
            }
        } elseif (!$append && (($ns = $GLOBALS['imp_imap']->getNamespace($folder)) !== null)) {
            /* Converting to preference value. */
            if ($ns['name'] == $def_ns['name']) {
                /* From personal namespace => strip namespace. */
                $folder = substr($folder, strlen($def_ns['name']));
            } elseif ($ns['name'] == $empty_ns['name']) {
                /* From empty namespace => prefix with delimiter. */
                $folder = $empty_ns['delimiter'] . $folder;
            }
        }

        return $folder;
    }

    /**
     * Generates a URL with necessary mailbox/UID information.
     *
     * @param string $page      Page name to link to.
     * @param string $mailbox   The base mailbox to use on the linked page.
     * @param string $uid       The UID to use on the linked page.
     * @param string $tmailbox  The mailbox associated with $uid.
     * @param boolean $encode   Encode the argument separator?
     *
     * @return string  URL to $page with any necessary mailbox information
     *                 added to the parameter list of the URL.
     */
    static public function generateIMPUrl($page, $mailbox, $uid = null,
                                          $tmailbox = null, $encode = true)
    {
        return Horde_Util::addParameter(Horde::applicationUrl($page), self::getIMPMboxParameters($mailbox, $uid, $tmailbox), null, $encode);
    }

    /**
     * Returns a list of parameters necessary to indicate current mailbox
     * status.
     *
     * @param string $mailbox   The mailbox to use on the linked page.
     * @param string $uid       The UID to use on the linked page.
     * @param string $tmailbox  The mailbox associated with $uid to use on
     *                          the linked page.
     *
     * @return array  The list of parameters needed to indicate the current
     *                mailbox status.
     */
    static public function getIMPMboxParameters($mailbox, $uid = null,
                                                $tmailbox = null)
    {
        $params = array('mailbox' => $mailbox);
        if (!is_null($uid)) {
            $params['uid'] = $uid;
            if ($mailbox != $tmailbox) {
                $params['thismailbox'] = $tmailbox;
            }
        }
        return $params;
    }

    /**
     * Determine whether we're hiding deleted messages.
     *
     * @param string $mbox    The current mailbox.
     * @param boolean $force  Force a redetermination of the return value
     *                        (return value is normally cached after the first
     *                        call).
     *
     * @return boolean  True if deleted messages should be hidden.
     */
    static public function hideDeletedMsgs($mbox, $force = false)
    {
        $delhide = &self::$_delhide;

        if (is_null($delhide) || $force) {
            if ($GLOBALS['prefs']->getValue('use_vtrash')) {
                $delhide = !$GLOBALS['imp_search']->isVTrashFolder();
            } else {
                $sortpref = self::getSort();
                $delhide = ($GLOBALS['prefs']->getValue('delhide') &&
                            !$GLOBALS['prefs']->getValue('use_trash') &&
                            ($GLOBALS['imp_search']->isSearchMbox($mbox) ||
                             ($sortpref['by'] != Horde_Imap_Client::SORT_THREAD)));
            }
        }

        return $delhide;
    }

    /**
     * Return a list of valid encrypt HTML option tags.
     *
     * @param string $default      The default encrypt option.
     * @param boolean $returnList  Whether to return a hash with options
     *                             instead of the options tag.
     *
     * @return string  The list of option tags.
     */
    static public function encryptList($default = null, $returnList = false)
    {
        if (is_null($default)) {
            $default = $GLOBALS['prefs']->getValue('default_encrypt');
        }

        $enc_opts = array(self::ENCRYPT_NONE => _("No Encryption"));
        $output = '';

        if (!empty($GLOBALS['conf']['gnupg']['path']) &&
            $GLOBALS['prefs']->getValue('use_pgp')) {
            $enc_opts += array(
                self::PGP_ENCRYPT => _("PGP Encrypt Message"),
                self::PGP_SIGN => _("PGP Sign Message"),
                self::PGP_SIGNENC => _("PGP Sign/Encrypt Message"),
                self::PGP_SYM_ENCRYPT => _("PGP Encrypt Message with passphrase"),
                self::PGP_SYM_SIGNENC => _("PGP Sign/Encrypt Message with passphrase")
            );
        }
        if ($GLOBALS['prefs']->getValue('use_smime')) {
            $enc_opts += array(
                self::SMIME_ENCRYPT => _("S/MIME Encrypt Message"),
                self::SMIME_SIGN => _("S/MIME Sign Message"),
                self::SMIME_SIGNENC => _("S/MIME Sign/Encrypt Message")
            );
        }

        if ($returnList) {
            return $enc_opts;
        }

        foreach ($enc_opts as $key => $val) {
             $output .= '<option value="' . $key . '"' . (($default == $key) ? ' selected="selected"' : '') . '>' . $val . "</option>\n";
        }

        return $output;
    }

    /**
     * Return the sorting preference for the current mailbox.
     *
     * @param string $mbox  The mailbox to use (defaults to current mailbox
     *                      in the session).
     *
     * @return array  An array with the following keys:
     *                'by'  - Sort type (Horde_Imap_Client constant)
     *                'dir' - Sort direction
     *                'limit' - Was the sort limit reached?
     */
    static public function getSort($mbox = null)
    {
        if (is_null($mbox)) {
            $mbox = $GLOBALS['imp_mbox']['mailbox'];
        }

        $search_mbox = $GLOBALS['imp_search']->isSearchMbox($mbox);
        $prefmbox = $search_mbox ? $mbox : self::folderPref($mbox, false);

        $sortpref = @unserialize($GLOBALS['prefs']->getValue('sortpref'));
        $entry = (isset($sortpref[$prefmbox])) ? $sortpref[$prefmbox] : array();

        if (!isset($entry['b'])) {
            $sortby = $GLOBALS['prefs']->getValue('sortby');
        }

        $ob = array(
            'by' => isset($entry['b']) ? $entry['b'] : $sortby,
            'dir' => isset($entry['d']) ? $entry['d'] : $GLOBALS['prefs']->getValue('sortdir'),
            'limit' => false
        );

        /* Restrict POP3 sorting to arrival only.  Although possible to
         * abstract other sorting methods, all other methods require a
         * download of all messages, which is too much overhead.*/
        if ($_SESSION['imp']['protocol'] == 'pop') {
            $ob['by'] = Horde_Imap_Client::SORT_ARRIVAL;
            $ob['limit'] = true;
            return $ob;
        }

        /* Can't do threaded searches in search mailboxes. */
        if (!self::threadSortAvailable($mbox) &&
            ($ob['by'] == Horde_Imap_Client::SORT_THREAD)) {
            $ob['by'] = Horde_Imap_Client::SORT_DATE;
        }

        if (!$search_mbox &&
            !empty($GLOBALS['conf']['server']['sort_limit'])) {
            try {
                $status = $GLOBALS['imp_imap']->ob()->status($mbox, Horde_Imap_Client::STATUS_MESSAGES);
                if (isset($status['messages']) &&
                    ($status['messages'] > $GLOBALS['conf']['server']['sort_limit'])) {
                    $ob['limit'] = true;
                    $ob['by'] = Horde_Imap_Client::SORT_ARRIVAL;
                }
            } catch (Horde_Imap_Client_Exception $e) {}
        }

        if (!$ob['limit']) {
            if (self::isSpecialFolder($mbox)) {
                /* If the preference is to sort by From Address, when we are
                 * in the Drafts or Sent folders, sort by To Address. */
                if ($ob['by'] == Horde_Imap_Client::SORT_FROM) {
                    $ob['by'] = Horde_Imap_Client::SORT_TO;
                }
            } elseif ($ob['by'] == Horde_Imap_Client::SORT_TO) {
                $ob['by'] = Horde_Imap_Client::SORT_FROM;
            }
        }

        return $ob;
    }

    /**
     * Determines if thread sorting is available.
     *
     * @param string $mbox  The mailbox to check.
     *
     * @return boolean  True if thread sort is available for this mailbox.
     */
    static public function threadSortAvailable($mbox)
    {
        /* Thread sort is always available for IMAP servers, since
         * Horde_Imap_Client_Socket has a built-in ORDEREDSUBJECT
         * implementation. We will always prefer REFERENCES, but will fallback
         * to ORDEREDSUBJECT if the server doesn't support THREAD sorting. */
        return ($_SESSION['imp']['protocol'] == 'imap') &&
               !$GLOBALS['imp_search']->isSearchMbox($mbox) &&
               (!$GLOBALS['prefs']->getValue('use_trash') ||
                !$GLOBALS['prefs']->getValue('use_vtrash') ||
                $GLOBALS['imp_search']->isVTrashFolder($mbox));
    }

    /**
     * Set the sorting preference for the current mailbox.
     *
     * @param integer $by      The sort type.
     * @param integer $dir     The sort direction.
     * @param string $mbox     The mailbox to use (defaults to current mailbox
     *                         in the session).
     * @param boolean $delete  Delete the entry?
     */
    static public function setSort($by = null, $dir = null, $mbox = null,
                                   $delete = false)
    {
        $entry = array();
        $sortpref = @unserialize($GLOBALS['prefs']->getValue('sortpref'));

        if (is_null($mbox)) {
            $mbox = $GLOBALS['imp_mbox']['mailbox'];
        }

        $prefmbox = $GLOBALS['imp_search']->isSearchMbox($mbox)
            ? $mbox
            : self::folderPref($mbox, false);

        if ($delete) {
            unset($sortpref[$prefmbox]);
        } else {
            if (!is_null($by)) {
                $entry['b'] = $by;
            }
            if (!is_null($dir)) {
                $entry['d'] = $dir;
            }

            if (!empty($entry)) {
                $sortpref[$prefmbox] = isset($sortpref[$prefmbox])
                    ? array_merge($sortpref[$prefmbox], $entry)
                    : $entry;
            }
        }

        if ($delete || !empty($entry)) {
            $GLOBALS['prefs']->setValue('sortpref', serialize($sortpref));
        }
    }

    /**
     * Is $mbox a 'special' folder (e.g. 'drafts' or 'sent-mail' folder)?
     *
     * @param string $mbox  The mailbox to query.
     *
     * @return boolean  Is $mbox a 'special' folder?
     */
    static public function isSpecialFolder($mbox)
    {
        /* Get the identities. */
        $identity = Horde_Prefs_Identity::singleton(array('imp', 'imp'));

        return (($mbox == self::folderPref($GLOBALS['prefs']->getValue('drafts_folder'), true)) || in_array($mbox, $identity->getAllSentmailFolders()));
    }

    /**
     * Sets mailbox/index information for current page load.
     * Sets the global $imp_search object.
     *
     * The global $imp_mbox objects will contain an array with the following
     * elements:
     * <pre>
     * 'mailbox' - (string) The current active mailbox (may be search mailbox).
     * 'thismailbox' -(string) The real IMAP mailbox of the current index.
     * 'uid' - (integer) The IMAP UID.
     * </pre>
     *
     * @param boolean $mbox  Use this mailbox, instead of form data.
     */
    static public function setCurrentMailboxInfo($mbox = null)
    {
        if (is_null($mbox)) {
            $mbox = Horde_Util::getFormData('mailbox');
            $GLOBALS['imp_mbox'] = array(
                'mailbox' => empty($mbox) ? 'INBOX' : $mbox,
                'thismailbox' => Horde_Util::getFormData('thismailbox', $mbox),
                'uid' => Horde_Util::getFormData('uid')
            );
        } else {
            $GLOBALS['imp_mbox'] = array(
                'mailbox' => $mbox,
                'thismailbox' => $mbox,
                'uid' => null
            );
        }

        // Initialize IMP_Search object.
        $GLOBALS['imp_search'] = new IMP_Search(array('id' => (isset($_SESSION['imp']) && IMP_Search::isSearchMbox($GLOBALS['imp_mbox']['mailbox'])) ? $GLOBALS['imp_mbox']['mailbox'] : null));
    }

    /**
     * Returns a Horde_Cache object (if configured) and handles any errors
     * associated with creating the object.
     *
     * @return mixed  A Horde_Cache object or null.
     * @throws Horde_Exception
     */
    public static function getCache()
    {
        $cache = Horde_Cache::singleton($GLOBALS['conf']['cache']['driver'], Horde::getDriverConfig('cache', $GLOBALS['conf']['cache']['driver']));
        return ($cache instanceof Horde_Cache_Null)
            ? null
            : $cache;
    }

    /**
     * Generate the JS code necessary to open a passphrase dialog. Adds the
     * necessary JS files to open the dialog.
     *
     * @param string $type    The dialog type.
     * @param string $action  The JS code to run after success. Defaults to
     *                        reloading the current window.
     * @param array $params   Any additional parameters to pass.
     *
     * @return string  The generated JS code.
     */
    static public function passphraseDialogJS($type, $action = null,
                                              $params = array())
    {
        Horde::addScriptFile('effects.js', 'horde');
        Horde::addScriptFile('redbox.js', 'horde');
        Horde::addScriptFile('dialog.js', 'imp');

        switch ($type) {
        case 'PGPPersonal':
            $text = _("Enter your personal PGP passphrase.");
            break;

        case 'PGPSymmetric':
            $text = _("Enter the passphrase used to encrypt this message.");
            break;

        case 'SMIMEPersonal':
            $text = _("Enter your personal S/MIME passphrase.");
            break;
        }

        if (defined('SID')) {
            parse_str(SID, $sid);
            $params = array_merge($params, $sid);
        }

        $js_params = array(
            'action' => $action,
            'uri' => Horde::applicationUrl('ajax.php', true, -1) . '/' . $type,
            'params' => $params,
            'text' => $text,
            'password' => true,
            'ok_text' => _("OK"),
            'cancel_text' => _("Cancel")
        );

        return 'IMPDialog.display(' . Horde::escapeJson($js_params, array('urlencode' => true)) . ')';
    }

    /**
     * Return a selfURL that has had index/mailbox/actionID information
     * removed/altered based on an action that has occurred on the present
     * page.
     *
     * @return string  The self URL.
     */
    static public function selfUrl()
    {
        return self::$newUrl ? self::$newUrl : Horde::selfUrl(true);
    }

    /**
     * Determine the status of composing.
     *
     * @return boolean  Is compose allowed?
     * @throws Horde_Exception
     */
    static public function canCompose()
    {
        try {
            return !Horde::callHook('disable_compose', array(), 'imp');
        } catch (Horde_Exception_HookNotSet $e) {
            return true;
        }
    }

}
