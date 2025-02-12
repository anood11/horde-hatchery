<?php
/**
 * The Ingo_Script_Maildrop:: class represents a maildrop script generator.
 *
 * Copyright 2005-2007 Matt Weyland <mathias@weyland.ch>
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author  Matt Weyland <mathias@weyland.ch>
 * @package Ingo
 */

/**
 * Additional storage action since maildrop does not support the
 * "c-flag" as in procmail.
 */
define('MAILDROP_STORAGE_ACTION_STOREANDFORWARD', 100);

/**
 */
class Ingo_Script_Maildrop extends Ingo_Script {

    /**
     * The list of actions allowed (implemented) for this driver.
     *
     * @var array
     */
    var $_actions = array(
        Ingo_Storage::ACTION_KEEP,
        Ingo_Storage::ACTION_MOVE,
        Ingo_Storage::ACTION_DISCARD,
        Ingo_Storage::ACTION_REDIRECT,
        Ingo_Storage::ACTION_REDIRECTKEEP,
        Ingo_Storage::ACTION_REJECT,
    );

    /**
     * The categories of filtering allowed.
     *
     * @var array
     */
    var $_categories = array(
        Ingo_Storage::ACTION_BLACKLIST,
        Ingo_Storage::ACTION_WHITELIST,
        Ingo_Storage::ACTION_VACATION,
        Ingo_Storage::ACTION_FORWARD,
        Ingo_Storage::ACTION_SPAM,
    );

    /**
     * The types of tests allowed (implemented) for this driver.
     *
     * @var array
     */
    var $_types = array(
        Ingo_Storage::TYPE_HEADER,
    );

    /**
     * The list of tests allowed (implemented) for this driver.
     *
     * @var array
     */
    var $_tests = array(
        'contains', 'not contain',
        'is', 'not is',
        'begins with','not begins with',
        'ends with', 'not ends with',
        'regex',
        'matches', 'not matches',
        'exists', 'not exist',
        'less than', 'less than or equal to',
        'equal', 'not equal',
        'greater than', 'greater than or equal to',
    );

    /**
     * Can tests be case sensitive?
     *
     * @var boolean
     */
    var $_casesensitive = true;

    /**
     * Does the driver support the stop-script option?
     *
     * @var boolean
     */
    var $_supportStopScript = false;

    /**
     * Does the driver require a script file to be generated?
     *
     * @var boolean
     */
    var $_scriptfile = true;

    /**
     * The recipes that make up the code.
     *
     * @var array
     */
    var $_recipes = array();

    /**
     * Returns a script previously generated with generate().
     *
     * @return string  The maildrop script.
     */
    function toCode()
    {
        $code = '';
        foreach ($this->_recipes as $item) {
            $code .= $item->generate() . "\n";
        }
        return rtrim($code);
    }

    /**
     * Generates the maildrop script to do the filtering specified in
     * the rules.
     *
     * @return string  The maildrop script.
     */
    function generate()
    {
        $filters = $GLOBALS['ingo_storage']->retrieve(Ingo_Storage::ACTION_FILTERS);

        $this->addItem(new Maildrop_Comment(_("maildrop script generated by Ingo") . ' (' . date('F j, Y, g:i a') . ')'));

        /* Add variable information, if present. */
        if (!empty($this->_params['variables']) &&
            is_array($this->_params['variables'])) {
            foreach ($this->_params['variables'] as $key => $val) {
                $this->addItem(new Maildrop_Variable(array('name' => $key, 'value' => $val)));
            }
        }

        foreach ($filters->getFilterList() as $filter) {
            switch ($filter['action']) {
            case Ingo_Storage::ACTION_BLACKLIST:
                $this->generateBlacklist(!empty($filter['disable']));
                break;

            case Ingo_Storage::ACTION_WHITELIST:
                $this->generateWhitelist(!empty($filter['disable']));
                break;

            case Ingo_Storage::ACTION_FORWARD:
                $this->generateForward(!empty($filter['disable']));
                break;

            case Ingo_Storage::ACTION_VACATION:
                $this->generateVacation(!empty($filter['disable']));
                break;

            case Ingo_Storage::ACTION_SPAM:
                $this->generateSpamfilter(!empty($filter['disable']));
                break;

            default:
                if (in_array($filter['action'], $this->_actions)) {
                    /* Create filter if using AND. */
                    $recipe = new Maildrop_Recipe($filter, $this->_params);
                    foreach ($filter['conditions'] as $condition) {
                        $recipe->addCondition($condition);
                    }
                    $this->addItem(new Maildrop_Comment($filter['name'], !empty($filter['disable']), true));
                    $this->addItem($recipe);
                }
            }
        }

        return $this->toCode();
    }

    /**
     * Generates the maildrop script to handle the blacklist specified in
     * the rules.
     *
     * @param boolean $disable  Disable the blacklist?
     */
    function generateBlacklist($disable = false)
    {
        $blacklist = &$GLOBALS['ingo_storage']->retrieve(Ingo_Storage::ACTION_BLACKLIST);
        $bl_addr = $blacklist->getBlacklist();
        $bl_folder = $blacklist->getBlacklistFolder();

        $bl_type = (empty($bl_folder)) ? Ingo_Storage::ACTION_DISCARD : Ingo_Storage::ACTION_MOVE;

        if (!empty($bl_addr)) {
            $this->addItem(new Maildrop_Comment(_("Blacklisted Addresses"), $disable, true));
            $params = array('action-value' => $bl_folder,
                            'action' => $bl_type,
                            'disable' => $disable);

            foreach ($bl_addr as $address) {
                if (!empty($address)) {
                    $recipe = new Maildrop_Recipe($params, $this->_params);
                    $recipe->addCondition(array('field' => 'From', 'value' => $address));
                    $this->addItem($recipe);
                }
            }
        }
    }

    /**
     * Generates the maildrop script to handle the whitelist specified in
     * the rules.
     *
     * @param boolean $disable  Disable the whitelist?
     */
    function generateWhitelist($disable = false)
    {
        $whitelist = &$GLOBALS['ingo_storage']->retrieve(Ingo_Storage::ACTION_WHITELIST);
        $wl_addr = $whitelist->getWhitelist();

        if (!empty($wl_addr)) {
            $this->addItem(new Maildrop_Comment(_("Whitelisted Addresses"), $disable, true));
            foreach ($wl_addr as $address) {
                if (!empty($address)) {
                    $recipe = new Maildrop_Recipe(array('action' => Ingo_Storage::ACTION_KEEP, 'disable' => $disable), $this->_params);
                    $recipe->addCondition(array('field' => 'From', 'value' => $address));
                    $this->addItem($recipe);
                }
            }
        }
    }

    /**
     * Generates the maildrop script to handle mail forwards.
     *
     * @param boolean $disable  Disable forwarding?
     */
    function generateForward($disable = false)
    {
        $forward = &$GLOBALS['ingo_storage']->retrieve(Ingo_Storage::ACTION_FORWARD);
        $addresses = $forward->getForwardAddresses();

        if (!empty($addresses)) {
            $this->addItem(new Maildrop_Comment(_("Forwards"), $disable, true));
            $params = array('action' => Ingo_Storage::ACTION_FORWARD,
                            'action-value' => $addresses,
                            'disable' => $disable);
            if ($forward->getForwardKeep()) {
                $params['action'] = MAILDROP_STORAGE_ACTION_STOREANDFORWARD;
            }
            $recipe = new Maildrop_Recipe($params, $this->_params);
            $recipe->addCondition(array('field' => 'From', 'value' => ''));
            $this->addItem($recipe);
        }
    }

    /**
     * Generates the maildrop script to handle vacation messages.
     *
     * @param boolean $disable  Disable forwarding?
     */
    function generateVacation($disable = false)
    {
        $vacation = &$GLOBALS['ingo_storage']->retrieve(Ingo_Storage::ACTION_VACATION);
        $addresses = $vacation->getVacationAddresses();
        $actionval = array('addresses' => $addresses,
                           'subject' => $vacation->getVacationSubject(),
                           'days' => $vacation->getVacationDays(),
                           'reason' => $vacation->getVacationReason(),
                           'ignorelist' => $vacation->getVacationIgnorelist(),
                           'excludes' => $vacation->getVacationExcludes(),
                           'start' => $vacation->getVacationStart(),
                           'end' => $vacation->getVacationEnd());

        if (!empty($addresses)) {
            $this->addItem(new Maildrop_Comment(_("Vacation"), $disable, true));
            $params = array('action' => Ingo_Storage::ACTION_VACATION,
                            'action-value' => $actionval,
                            'disable' => $disable);
            $recipe = new Maildrop_Recipe($params, $this->_params);
            $this->addItem($recipe);
        }
    }

    /**
     * Generates the maildrop script to handle spam as identified by SpamAssassin
     *
     * @param boolean $disable  Disable the spam-filter?
     */
    function generateSpamfilter($disable = false)
    {
        $spam = &$GLOBALS['ingo_storage']->retrieve(Ingo_Storage::ACTION_SPAM);
        if ($spam == false) {
            return;
        }

        $spam_folder = $spam->getSpamFolder();
        $spam_action = (empty($spam_folder)) ? Ingo_Storage::ACTION_DISCARD : Ingo_Storage::ACTION_MOVE;

        $this->addItem(new Maildrop_Comment(_("Spam Filter"), $disable, true));

        $params = array('action-value' => $spam_folder,
                        'action' => $spam_action,
                        'disable' => $disable);
        $recipe = new Maildrop_Recipe($params, $this->_params);
        if ($this->_params['spam_compare'] == 'numeric') {
            $recipe->addCondition(array('match' => 'greater than or equal to',
                                        'field' => $this->_params['spam_header'],
                                        'value' => $spam->getSpamLevel()));
        } elseif ($this->_params['spam_compare'] == 'string') {
            $recipe->addCondition(array('match' => 'contains',
                                        'field' => $this->_params['spam_header'],
                                        'value' => str_repeat($this->_params['spam_char'], $spam->getSpamLevel())));
        }

        $this->addItem($recipe);
    }

    /**
     * Adds an item to the recipe list.
     *
     * @param object $item  The item to add to the recipe list.
     *                      The object should have a generate() function.
     */
    function addItem($item)
    {
        $this->_recipes[] = $item;
    }

}

/**
 * The Maildrop_Comment:: class represents a maildrop comment.  This is
 * a pretty simple class, but it makes the code in Ingo_Script_Maildrop::
 * cleaner as it provides a generate() function and can be added to the
 * recipe list the same way as a recipe can be.
 *
 * @author  Matt Weyland <mathias@weyland.ch>
 * @package Ingo
 */
class Maildrop_Comment {

    /**
     * The comment text.
     *
     * @var string
     */
    var $_comment = '';

    /**
     * Constructs a new maildrop comment.
     *
     * @param string $comment   Comment to be generated.
     * @param boolean $disable  Output 'DISABLED' comment?
     * @param boolean $header   Output a 'header' comment?
     */
    function Maildrop_Comment($comment, $disable = false, $header = false)
    {
        if ($disable) {
            $comment = _("DISABLED: ") . $comment;
        }

        if ($header) {
            $this->_comment .= "##### $comment #####";
        } else {
            $this->_comment .= "# $comment";
        }
    }

    /**
     * Returns the comment stored by this object.
     *
     * @return string  The comment stored by this object.
     */
    function generate()
    {
        return $this->_comment;
    }

}

/**
 * The Maildrop_Recipe:: class represents a maildrop recipe.
 *
 * @author  Matt Weyland <mathias@weyland.ch>
 * @package Ingo
 */
class Maildrop_Recipe {

    var $_action = array();
    var $_conditions = array();
    var $_disable = '';
    var $_flags = '';
    var $_params = array();
    var $_combine = '';
    var $_valid = true;

    var $_operators = array(
        'less than'                => '<',
        'less than or equal to'    => '<=',
        'equal'                    => '==',
        'not equal'                => '!=',
        'greater than'             => '>',
        'greater than or equal to' => '>=',
    );

    /**
     * Constructs a new maildrop recipe.
     *
     * @param array $params        Array of parameters.
     *                             REQUIRED FIELDS:
     *                             'action'
     *                             OPTIONAL FIELDS:
     *                             'action-value' (only used if the
     *                             'action' requires it)
     * @param array $scriptparams  Array of parameters passed to
     *                             Ingo_Script_Maildrop::.
     */
    function Maildrop_Recipe($params = array(), $scriptparams = array())
    {
        $this->_disable = !empty($params['disable']);
        $this->_params = $scriptparams;
        $this->_action[] = 'exception {';

        switch ($params['action']) {
        case Ingo_Storage::ACTION_KEEP:
            $this->_action[] = '   to "${DEFAULT}"';
            break;

        case Ingo_Storage::ACTION_MOVE:
            $this->_action[] = '   to ' . $this->maildropPath($params['action-value']);
            break;

        case Ingo_Storage::ACTION_DISCARD:
            $this->_action[] = '   exit';
            break;

        case Ingo_Storage::ACTION_REDIRECT:
            $this->_action[] = '   to "! ' . $params['action-value'] . '"';
            break;

        case Ingo_Storage::ACTION_REDIRECTKEEP:
            $this->_action[] = '   cc "! ' . $params['action-value'] . '"';
            $this->_action[] = '   to "${DEFAULT}"';
            break;

        case Ingo_Storage::ACTION_REJECT:
            $this->_action[] = '   EXITCODE=77'; # EX_NOPERM (permanent failure)
            $this->_action[] = '   echo "5.7.1 ' . $params['action-value'] . '"';
            $this->_action[] = '   exit';
            break;

        case Ingo_Storage::ACTION_VACATION:
            $from = '';
            foreach ($params['action-value']['addresses'] as $address) {
                $from = $address;
            }

            /**
             * @TODO
             *
             * Exclusion and listfilter
             */
            $exclude = '';
            foreach ($params['action-value']['excludes'] as $address) {
                $exclude .= $address . ' ';
            }

            $start = strftime($params['action-value']['start']);
            if ($start === false) {
                $start = 0;
            }
            $end = strftime($params['action-value']['end']);
            if ($end === false) {
                $end = 0;
            }
            $days = strftime($params['action-value']['days']);
            if ($days === false) {
                // Set to same value as $_days in ingo/lib/Storage.php
                $days = 7;
            }

            // Writing vacation.msg file
            $reason = Horde_Mime::encode($params['action-value']['reason'], $scriptparams['charset']);
            $driver = Ingo::getDriver();
            $driver->_connect();
            $result = $driver->_vfs->writeData($driver->_params['vfs_path'], 'vacation.msg', $reason, true);

            // Rule : Do not send responses to bulk or list messages
            if ($params['action-value']['ignorelist'] == 1) {
                $params['combine'] = Ingo_Storage::COMBINE_ALL;
                $this->addCondition(array('match' => 'filter', 'field' => '', 'value' => '! /^Precedence: (bulk|list|junk)/'));
                $this->addCondition(array('match' => 'filter', 'field' => '', 'value' => '! /^Return-Path:.*<#@\[\]>/'));
                $this->addCondition(array('match' => 'filter', 'field' => '', 'value' => '! /^Return-Path:.*<>/'));
                $this->addCondition(array('match' => 'filter', 'field' => '', 'value' => '! /^From:.*MAILER-DAEMON/'));
                $this->addCondition(array('match' => 'filter', 'field' => '', 'value' => '! /^X-ClamAV-Notice-Flag: *YES/'));
                $this->addCondition(array('match' => 'filter', 'field' => '', 'value' => '! /^Content-Type:.*message\/delivery-status/'));
                $this->addCondition(array('match' => 'filter', 'field' => '', 'value' => '! /^Subject:.*Delivery Status Notification/'));
                $this->addCondition(array('match' => 'filter', 'field' => '', 'value' => '! /^Subject:.*Undelivered Mail Returned to Sender/'));
                $this->addCondition(array('match' => 'filter', 'field' => '', 'value' => '! /^Subject:.*Delivery failure/'));
                $this->addCondition(array('match' => 'filter', 'field' => '', 'value' => '! /^Subject:.*Message delay/'));
                $this->addCondition(array('match' => 'filter', 'field' => '', 'value' => '! /^Subject:.*Mail Delivery Subsystem/'));
                $this->addCondition(array('match' => 'filter', 'field' => '', 'value' => '! /^Subject:.*Mail System Error.*Returned Mail/'));
                $this->addCondition(array('match' => 'filter', 'field' => '', 'value' => '! /^X-Spam-Flag: YES/ '));
            } else {
                $this->addCondition(array('field' => 'From', 'value' => ''));
            }

            // Rule : Start/End of vacation
            if (($start != 0) && ($end !== 0)) {
                $this->_action[] = '  flock "vacationprocess.lock" {';
                $this->_action[] = '    current_time=time';
                $this->_action[] = '      if ( \ ';
                $this->_action[] = '        ($current_time >= ' . $start . ') && \ ';
                $this->_action[] = '        ($current_time <= ' . $end . ')) ';
                $this->_action[] = '      {';
            }
            $this->_action[] = "  cc \"| mailbot -D " . $params['action-value']['days'] . " -c '" . $scriptparams['charset'] . "' -t \$HOME/vacation.msg -d \$HOME/vacation -A 'From: $from' -s '" . Horde_Mime::encode($params['action-value']['subject'], $scriptparams['charset'])  . "' /usr/sbin/sendmail -t \"";
            if (($start != 0) && ($end !== 0)) {
                $this->_action[] = '      }';
                $this->_action[] = '  }';
            }

            break;

        case Ingo_Storage::ACTION_FORWARD:
        case MAILDROP_STORAGE_ACTION_STOREANDFORWARD:
            foreach ($params['action-value'] as $address) {
                if (!empty($address)) {
                    $this->_action[] = '  cc "! ' . $address . '"';
                }
            }

            /* The 'to' must be the last action, because maildrop
             * stops processing after it. */
            if ($params['action'] == MAILDROP_STORAGE_ACTION_STOREANDFORWARD) {
                $this->_action[] = ' to "${DEFAULT}"';
            } else {
                $this->_action[] = ' exit';
            }
            break;

        default:
            $this->_valid = false;
            break;
        }

        $this->_action[] = '}';

        if (isset($params['combine']) &&
            ($params['combine'] == Ingo_Storage::COMBINE_ALL)) {
            $this->_combine = '&& ';
        } else {
            $this->_combine = '|| ';
        }
    }

    /**
     * Adds a flag to the recipe.
     *
     * @param string $flag  String of flags to append to the current flags.
     */
    function addFlag($flag)
    {
        $this->_flags .= $flag;
    }

    /**
     * Adds a condition to the recipe.
     *
     * @param optonal array $condition  Array of parameters. Required keys
     *                                  are 'field' and 'value'. 'case' is
     *                                  an optional keys.
     */
    function addCondition($condition = array())
    {
        $flag = (!empty($condition['case'])) ? 'D' : '';
        if (empty($this->_conditions)) {
            $this->addFlag($flag);
        }

        $string = '';
        $extra = '';

        $match = (isset($condition['match'])) ? $condition['match'] : null;
        // negate tests starting with 'not ', except 'not equals', which simply uses the != operator
        if ($match != 'not equal' && substr($match, 0, 4) == 'not ') {
            $string .= '! ';
        }

        // convert 'field' to PCRE pattern matching
        if (strpos($condition['field'], ',') == false) {
            $string .= '/^' . $condition['field'] . ':\\s*';
        } else {
            $string .= '/^(' . str_replace(',', '|', $condition['field']) . '):\\s*';
        }

        switch ($match) {
        case 'not regex':
        case 'regex':
            $string .= $condition['value'] . '/:h';
            break;

        case 'filter':
            $string = $condition['value'];
            break;

        case 'exists':
        case 'not exist':
            // Just run a match for the header name
            $string .= '/:h';
            break;

        case 'less than or equal to':
        case 'less than':
        case 'equal':
        case 'not equal':
        case 'greater than or equal to':
        case 'greater than':
            $string .= '(\d+(\.\d+)?)/:h';
            $extra = ' && $MATCH1 ' . $this->_operators[$match] . ' ' . (int)$condition['value'];
            break;

        case 'begins with':
        case 'not begins with':
            $string .= preg_quote($condition['value'], '/') . '/:h';
            break;

        case 'ends with':
        case 'not ends with':
            $string .= '.*' . preg_quote($condition['value'], '/') . '$/:h';
            break;

        case 'is':
        case 'not is':
            $string .= preg_quote($condition['value'], '/') . '$/:h';
            break;

        case 'matches':
        case 'not matches':
            $string .= str_replace(array('\\*', '\\?'), array('.*', '.'), preg_quote($condition['value'], '/') . '$') . '/:h';
            break;

        case 'contains':
        case 'not contain':
        default:
            $string .= '.*' . preg_quote($condition['value'], '/') . '/:h';
            break;
        }

        $this->_conditions[] = array('condition' => $string, 'flags' => $flag, 'extra' => $extra);
    }

    /**
     * Generates maildrop code to represent the recipe.
     *
     * @return string  maildrop code to represent the recipe.
     */
    function generate()
    {
        $text = array();

        if (!$this->_valid) {
            return '';
        }

        if (count($this->_conditions) > 0) {

            $text[] = "if( \\";

            $nest = false;
            foreach ($this->_conditions as $condition) {
                $cond = $nest ? $this->_combine : '   ';
                $text[] = $cond . $condition['condition'] . $condition['flags'] . $condition['extra'] . " \\";
                $nest = true;
            }

            $text[] = ')';
        }

        foreach ($this->_action as $val) {
            $text[] = $val;
        }

        if ($this->_disable) {
            $code = '';
            foreach ($text as $val) {
                $comment = new Maildrop_Comment($val);
                $code .= $comment->generate() . "\n";
            }
            return $code . "\n";
        } else {
            return implode("\n", $text) . "\n";
        }
    }

    /**
     * Returns a maildrop-ready mailbox path, converting IMAP folder pathname
     * conventions as necessary.
     *
     * @param string $folder  The IMAP folder name.
     *
     * @return string  The maildrop mailbox path.
     */
    function maildropPath($folder)
    {
        /* NOTE: '$DEFAULT' here is a literal, not a PHP variable. */
        if (isset($this->_params) &&
            ($this->_params['path_style'] == 'maildir')) {
            if (empty($folder) || ($folder == 'INBOX')) {
                return '"${DEFAULT}"';
            }
            if ($this->_params['strip_inbox'] &&
                substr($folder, 0, 6) == 'INBOX.') {
                $folder = substr($folder, 6);
            }
            return '"${DEFAULT}/.' . $folder . '/"';
        } else {
            if (empty($folder) || ($folder == 'INBOX')) {
                return '${DEFAULT}';
            }
            return str_replace(' ', '\ ', $folder);
        }
    }

}

/**
 * The Maildrop_Variable:: class represents a Maildrop variable.
 *
 * @author  Matt Weyland <mathias@weyland.ch>
 * @package Ingo
 */
class Maildrop_Variable {

    var $_name;
    var $_value;

    /**
     * Constructs a new maildrop variable.
     *
     * @param array $params  Array of parameters. Expected fields are 'name'
     *                       and 'value'.
     */
    function Maildrop_Variable($params = array())
    {
        $this->_name = $params['name'];
        $this->_value = $params['value'];
    }

    /**
     * Generates maildrop code to represent the variable.
     *
     * @return string  maildrop code to represent the variable.
     */
    function generate()
    {
        return $this->_name . '=' . $this->_value . "\n";
    }

}
